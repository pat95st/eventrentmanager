<?php

declare(strict_types=1);

namespace App\Application\Logbook;

use App\Domain\Logbook\LogbookThrottleRepositoryInterface;
use DateTimeImmutable;

/**
 * Protection of the capture pages (migration 185):
 * - odometer: after MAX_ODOMETER_FAILURES wrong readings per vehicle and
 *   client the check is locked for LOCK_MINUTES;
 * - submit: external drivers may record at most MAX_SUBMITS_PER_HOUR trips
 *   per vehicle and client and hour.
 */
final class LogbookThrottle
{
    public const MAX_ODOMETER_FAILURES = 5;
    public const LOCK_MINUTES = 15;
    public const MAX_SUBMITS_PER_HOUR = 10;

    public function __construct(private readonly LogbookThrottleRepositoryInterface $repository)
    {
    }

    /** Locked until (null = not locked). */
    public function odometerLockedUntil(string $vehicleId, string $client, ?DateTimeImmutable $now = null): ?DateTimeImmutable
    {
        $now ??= new DateTimeImmutable();
        $row = $this->repository->find($this->key('odometer', $vehicleId, $client));
        $until = $row['lockedUntil'] ?? null;

        return $until !== null && $until > $now ? $until : null;
    }

    /** Registers a wrong reading; returns the lock end once the limit is reached. */
    public function registerOdometerFailure(string $vehicleId, string $client, ?DateTimeImmutable $now = null): ?DateTimeImmutable
    {
        $now ??= new DateTimeImmutable();
        $key = $this->key('odometer', $vehicleId, $client);
        $row = $this->repository->find($key);
        $counter = $row !== null && ($row['lockedUntil'] === null || $row['lockedUntil'] > $now) && $row['windowStart'] > $now->modify('-1 hour') ? $row['counter'] + 1 : 1;
        $start = $counter === 1 ? $now : $row['windowStart'];
        $locked = null;
        if ($counter >= self::MAX_ODOMETER_FAILURES) {
            $locked = $now->modify('+' . self::LOCK_MINUTES . ' minutes');
            $counter = 0;
        }
        $this->repository->store($key, 'odometer', $counter, $start, $locked);
        $this->repository->purge($now->modify('-1 day'));

        return $locked;
    }

    public function resetOdometer(string $vehicleId, string $client): void
    {
        $this->repository->delete($this->key('odometer', $vehicleId, $client));
    }

    /** Counts a trip of an external driver; false = limit of the hour reached (nothing counted). */
    public function allowSubmit(string $vehicleId, string $client, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();
        $key = $this->key('submit', $vehicleId, $client);
        $row = $this->repository->find($key);
        if ($row === null || $row['windowStart'] <= $now->modify('-1 hour')) {
            $this->repository->store($key, 'submit', 1, $now, null);

            return true;
        }
        if ($row['counter'] >= self::MAX_SUBMITS_PER_HOUR) {
            return false;
        }
        $this->repository->store($key, 'submit', $row['counter'] + 1, $row['windowStart'], null);

        return true;
    }

    private function key(string $scope, string $vehicleId, string $client): string
    {
        return hash('sha256', $scope . '|' . $vehicleId . '|' . $client);
    }
}
