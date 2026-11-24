<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Logbook;

use App\Application\Logbook\LogbookThrottle;
use App\Domain\Logbook\LogbookThrottleRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;

/** Lock after wrong odometer readings and rate limit of external trips (migration 185). */
final class LogbookThrottleTest extends Unit
{
    private LogbookThrottle $throttle;

    protected function _before(): void
    {
        $this->throttle = new LogbookThrottle(new class () implements LogbookThrottleRepositoryInterface {
            /** @var array<string, array{counter: int, windowStart: DateTimeImmutable, lockedUntil: ?DateTimeImmutable}> */
            private array $rows = [];

            public function find(string $key): ?array
            {
                return $this->rows[$key] ?? null;
            }

            public function store(string $key, string $scope, int $counter, DateTimeImmutable $windowStart, ?DateTimeImmutable $lockedUntil): void
            {
                $this->rows[$key] = ['counter' => $counter, 'windowStart' => $windowStart, 'lockedUntil' => $lockedUntil];
            }

            public function delete(string $key): void
            {
                unset($this->rows[$key]);
            }

            public function purge(DateTimeImmutable $before): void
            {
            }
        });
    }

    public function testLocksAfterFiveWrongReadings(): void
    {
        $now = new DateTimeImmutable('2026-11-23 10:00');
        for ($i = 1; $i < LogbookThrottle::MAX_ODOMETER_FAILURES; $i++) {
            $this->assertNull($this->throttle->registerOdometerFailure('v1', 'ip:1', $now));
        }
        $locked = $this->throttle->registerOdometerFailure('v1', 'ip:1', $now);
        $this->assertEquals($now->modify('+15 minutes'), $locked);
        $this->assertNotNull($this->throttle->odometerLockedUntil('v1', 'ip:1', $now->modify('+10 minutes')));
        $this->assertNull($this->throttle->odometerLockedUntil('v1', 'ip:2', $now));
        $this->assertNull($this->throttle->odometerLockedUntil('v1', 'ip:1', $now->modify('+16 minutes')));
        // After the lock the counting starts again.
        $this->assertNull($this->throttle->registerOdometerFailure('v1', 'ip:1', $now->modify('+16 minutes')));
    }

    public function testSuccessResetsTheCounter(): void
    {
        $now = new DateTimeImmutable();
        for ($i = 1; $i < LogbookThrottle::MAX_ODOMETER_FAILURES; $i++) {
            $this->throttle->registerOdometerFailure('v1', 'ip:1', $now);
        }
        $this->throttle->resetOdometer('v1', 'ip:1');
        $this->assertNull($this->throttle->registerOdometerFailure('v1', 'ip:1', $now));
    }

    public function testRateLimitOfExternalTrips(): void
    {
        $now = new DateTimeImmutable('2026-11-23 10:00');
        for ($i = 0; $i < LogbookThrottle::MAX_SUBMITS_PER_HOUR; $i++) {
            $this->assertTrue($this->throttle->allowSubmit('v1', 'ip:1', $now));
        }
        $this->assertFalse($this->throttle->allowSubmit('v1', 'ip:1', $now->modify('+30 minutes')));
        $this->assertTrue($this->throttle->allowSubmit('v1', 'ip:1', $now->modify('+61 minutes')));
    }
}
