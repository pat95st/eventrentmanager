<?php

declare(strict_types=1);

namespace App\Domain\EventMessage;

use DateTimeImmutable;

/**
 * Message from the organizer (live page "Audio & Durchsagen") to the stage
 * display and/or the FOH run sheet page (migration 168).
 *
 * $minuteProgramId null = applies to all minute programs of the rental.
 * A message is active until it is closed (withdrawn by the sender or hidden
 * at FOH); $acknowledgedAt records that FOH has seen it.
 */
final class EventMessage
{
    public const MAX_LENGTH = 500;

    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly ?string $minuteProgramId,
        public readonly string $body,
        public readonly bool $toStage,
        public readonly bool $toFoh,
        public readonly bool $urgent,
        public readonly string $sentByType,
        public readonly ?string $sentById,
        public readonly string $sentByName,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $acknowledgedAt = null,
        public ?string $acknowledgedBy = null,
        public ?DateTimeImmutable $closedAt = null,
        public ?string $closedBy = null,
    ) {
    }

    public function isActive(): bool
    {
        return $this->closedAt === null;
    }

    /** Whether the message is meant for the given minute program (null program = all). */
    public function appliesTo(string $minuteProgramId): bool
    {
        return $this->minuteProgramId === null || $this->minuteProgramId === $minuteProgramId;
    }
}
