<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

/**
 * Zugang für Veranstalter zur Live-Oberfläche einer Veranstaltung
 * (/live, Migration 135): eigener Benutzername + Passwort, optional
 * zeitlich begrenzt. Unabhängig von den Benutzerkonten der Software.
 */
final class OrganizerLogin
{
    public const MAX_FAILED_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;

    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public string $label,
        public string $username,
        public string $passwordHash,
        public ?DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validUntil,
        public bool $enabled,
        public int $failedAttempts,
        public ?DateTimeImmutable $lockedUntil,
        public ?DateTimeImmutable $lastLoginAt,
        public readonly DateTimeImmutable $createdAt,
        // Migration 136: darf Einspieler zeitlich planen (inkl. eigener Audio-Uploads)
        public bool $canSchedule = false,
    ) {
    }

    public function isValidAt(DateTimeImmutable $now): bool
    {
        return $this->enabled
            && ($this->validFrom === null || $now >= $this->validFrom)
            && ($this->validUntil === null || $now <= $this->validUntil);
    }

    public function isLocked(DateTimeImmutable $now): bool
    {
        return $this->lockedUntil !== null && $now < $this->lockedUntil;
    }

    public function statusLabel(DateTimeImmutable $now): string
    {
        return match (true) {
            !$this->enabled => 'deaktiviert',
            $this->isLocked($now) => 'gesperrt (Fehlversuche)',
            $this->validFrom !== null && $now < $this->validFrom => 'noch nicht gültig',
            $this->validUntil !== null && $now > $this->validUntil => 'abgelaufen',
            default => 'aktiv',
        };
    }

    public static function generatePassword(): string
    {
        // Ohne leicht verwechselbare Zeichen (0/O, 1/l/I), gut diktierbar.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
