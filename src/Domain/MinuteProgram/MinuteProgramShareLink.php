<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

use DateTimeImmutable;

/**
 * Öffentlicher, token-gesicherter Freigabe-Link für genau ein
 * Minutenprogramm (siehe MinuteProgramShareController). Läuft bewusst NICHT
 * automatisch ab, sondern bleibt gültig, bis er aktiv widerrufen wird
 * (siehe RevokeMinuteProgramShareLinkHandler).
 */
final class MinuteProgramShareLink
{
    public function __construct(
        public readonly string $id,
        public readonly string $minuteProgramId,
        public readonly string $token,
        public readonly ShareLinkMode $mode,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $revokedAt,
    ) {
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }
}
