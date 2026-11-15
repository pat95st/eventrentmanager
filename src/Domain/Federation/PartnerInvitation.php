<?php

declare(strict_types=1);

namespace App\Domain\Federation;

use DateTimeImmutable;

/**
 * Invitation link for befriending another installation (migration 157).
 * Only the SHA-256 hash of the secret code is stored; the full link is shown
 * once right after it was created.
 */
final class PartnerInvitation
{
    public const VALIDITY_DAYS = 7;

    public function __construct(
        public readonly string $id,
        public readonly string $codeHash,
        public ?string $note,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
        public ?string $partnerId,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && $this->expiresAt > $now;
    }
}
