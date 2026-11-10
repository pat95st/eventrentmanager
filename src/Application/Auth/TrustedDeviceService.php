<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\TrustedDevice;
use App\Domain\Auth\TrustedDeviceRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * "Dem Gerät für die nächsten 10 Tage vertrauen" (Migration 143).
 *
 * Das Gerät bekommt ein Token der Form "selector:validator". Über den
 * selector wird der Datenbank-Eintrag gefunden, der validator wird nur als
 * SHA-256-Hash gespeichert und per hash_equals() (Konstantzeit) geprüft.
 * Ein Token gilt ausschließlich für den Benutzer, für den es ausgestellt
 * wurde — meldet sich auf demselben Gerät jemand anderes an, wird für
 * diesen weiterhin der zweite Faktor abgefragt.
 *
 * Das Cookie selbst setzt/liest App\Infrastructure\Http\Auth\TrustedDeviceCookie.
 */
final class TrustedDeviceService
{
    public const TRUST_DAYS = 10;

    public function __construct(private readonly TrustedDeviceRepositoryInterface $devices)
    {
    }

    /** Legt einen neuen Eintrag an und liefert das Token für das Cookie. */
    public function issue(string $userId, ?string $userAgent, ?string $ipAddress, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable();
        $this->devices->deleteExpired($now);

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        $this->devices->save(new TrustedDevice(
            id: Uuid::v4(),
            userId: $userId,
            selector: $selector,
            tokenHash: hash('sha256', $validator),
            userAgent: $userAgent !== null ? mb_substr($userAgent, 0, 1000) : null,
            ipAddress: $ipAddress,
            createdAt: $now,
            expiresAt: $now->modify('+' . self::TRUST_DAYS . ' days'),
        ));

        return $selector . ':' . $validator;
    }

    /** true, wenn das Token gültig, nicht abgelaufen und für genau diesen Benutzer ausgestellt ist. */
    public function isTrusted(?string $token, string $userId, ?DateTimeImmutable $now = null): bool
    {
        $device = $this->resolve($token, $now);

        return $device !== null && hash_equals($device->userId, $userId);
    }

    /** Entfernt den zum Token gehörenden Eintrag (falls vorhanden). */
    public function forget(?string $token): void
    {
        $device = $this->resolve($token);
        if ($device !== null) {
            $this->devices->deleteById($device->id);
        }
    }

    /** @return TrustedDevice[] */
    public function listForUser(string $userId, ?DateTimeImmutable $now = null): array
    {
        return $this->devices->findActiveByUser($userId, $now ?? new DateTimeImmutable());
    }

    public function revokeAllForUser(string $userId): void
    {
        $this->devices->deleteAllForUser($userId);
    }

    private function resolve(?string $token, ?DateTimeImmutable $now = null): ?TrustedDevice
    {
        if ($token === null || !preg_match('/^([0-9a-f]{24}):([0-9a-f]{64})$/', $token, $m)) {
            return null;
        }

        $device = $this->devices->findBySelector($m[1]);
        if ($device === null || !hash_equals($device->tokenHash, hash('sha256', $m[2]))) {
            return null;
        }

        if ($device->isExpired($now ?? new DateTimeImmutable())) {
            $this->devices->deleteById($device->id);

            return null;
        }

        return $device;
    }
}
