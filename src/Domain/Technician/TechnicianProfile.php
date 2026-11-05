<?php

declare(strict_types=1);

namespace App\Domain\Technician;

use DateTimeImmutable;

/**
 * Einstellungen eines Techniker-Benutzers (Migration 134): direkt verplanbar
 * oder Zustimmung nötig, sowie Messenger-Kontaktdaten für Anfragen.
 */
final class TechnicianProfile
{
    public function __construct(
        public readonly string $userId,
        public bool $requiresConfirmation,
        public ?string $phone,
        public ?string $telegramChatId,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function defaultFor(string $userId): self
    {
        return new self($userId, true, null, null, new DateTimeImmutable());
    }
}
