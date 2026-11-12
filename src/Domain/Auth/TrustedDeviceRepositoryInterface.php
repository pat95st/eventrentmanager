<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

interface TrustedDeviceRepositoryInterface
{
    public function save(TrustedDevice $device): void;

    public function findBySelector(string $selector): ?TrustedDevice;

    /** @return TrustedDevice[] Nur noch gültige Einträge, neueste zuerst. */
    public function findActiveByUser(string $userId, DateTimeImmutable $now): array;

    public function deleteById(string $id): void;

    /** Entzieht ALLEN Geräten eines Benutzers das Vertrauen (Passwort-/2FA-Änderung, manueller Widerruf). */
    public function deleteAllForUser(string $userId): void;

    public function deleteExpired(DateTimeImmutable $now): void;
}
