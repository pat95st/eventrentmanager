<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Auth;

use App\Application\Auth\TrustedDeviceService;
use App\Domain\Auth\TrustedDevice;
use App\Domain\Auth\TrustedDeviceRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;

/** "Dem Gerät für die nächsten 10 Tage vertrauen" (Migration 145). */
final class TrustedDeviceServiceTest extends Unit
{
    private TrustedDeviceService $service;
    private object $devices;

    protected function _before(): void
    {
        $this->devices = new class () implements TrustedDeviceRepositoryInterface {
            /** @var array<string, TrustedDevice> */
            public array $items = [];

            public function save(TrustedDevice $device): void
            {
                $this->items[$device->id] = $device;
            }

            public function findBySelector(string $selector): ?TrustedDevice
            {
                foreach ($this->items as $device) {
                    if ($device->selector === $selector) {
                        return $device;
                    }
                }

                return null;
            }

            public function findActiveByUser(string $userId, DateTimeImmutable $now): array
            {
                return array_values(array_filter($this->items, static fn (TrustedDevice $d) => $d->userId === $userId && !$d->isExpired($now)));
            }

            public function deleteById(string $id): void
            {
                unset($this->items[$id]);
            }

            public function deleteAllForUser(string $userId): void
            {
                $this->items = array_filter($this->items, static fn (TrustedDevice $d) => $d->userId !== $userId);
            }

            public function deleteExpired(DateTimeImmutable $now): void
            {
                $this->items = array_filter($this->items, static fn (TrustedDevice $d) => !$d->isExpired($now));
            }
        };
        $this->service = new TrustedDeviceService($this->devices);
    }

    public function testIssuedTokenIsTrustedForTenDaysForTheSameUser(): void
    {
        $now = new DateTimeImmutable('2026-11-10 12:00:00');
        $token = $this->service->issue('user-1', 'Firefox', '127.0.0.1', $now);

        $this->assertTrue($this->service->isTrusted($token, 'user-1', $now->modify('+9 days 23 hours')));
        $this->assertFalse($this->service->isTrusted($token, 'user-1', $now->modify('+10 days')));
    }

    public function testTokenIsNotTrustedForAnotherUser(): void
    {
        $token = $this->service->issue('user-1', null, null);

        $this->assertFalse($this->service->isTrusted($token, 'user-2'));
    }

    public function testOnlyTheHashOfTheSecretIsStored(): void
    {
        $token = $this->service->issue('user-1', null, null);
        [, $validator] = explode(':', $token);
        $stored = array_values($this->devices->items)[0];

        $this->assertNotSame($validator, $stored->tokenHash);
        $this->assertSame(hash('sha256', $validator), $stored->tokenHash);
    }

    public function testTamperedOrMalformedTokensAreRejected(): void
    {
        $token = $this->service->issue('user-1', null, null);
        [$selector] = explode(':', $token);

        $this->assertFalse($this->service->isTrusted($selector . ':' . str_repeat('0', 64), 'user-1'));
        $this->assertFalse($this->service->isTrusted('kaputt', 'user-1'));
        $this->assertFalse($this->service->isTrusted(null, 'user-1'));
    }

    public function testRevokeAllRemovesTrust(): void
    {
        $token = $this->service->issue('user-1', null, null);
        $this->service->revokeAllForUser('user-1');

        $this->assertFalse($this->service->isTrusted($token, 'user-1'));
        $this->assertSame([], $this->service->listForUser('user-1'));
    }

    public function testForgetRemovesOnlyThatDevice(): void
    {
        $first = $this->service->issue('user-1', null, null);
        $second = $this->service->issue('user-1', null, null);
        $this->service->forget($first);

        $this->assertFalse($this->service->isTrusted($first, 'user-1'));
        $this->assertTrue($this->service->isTrusted($second, 'user-1'));
    }
}
