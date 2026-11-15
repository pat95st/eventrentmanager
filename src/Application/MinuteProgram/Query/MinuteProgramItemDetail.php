<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgramItem;
use DateTimeImmutable;

final class MinuteProgramItemDetail
{
    public const STATUS_DONE = 'done';
    public const STATUS_CURRENT = 'current';
    public const STATUS_NEXT = 'next';
    public const STATUS_UPCOMING = 'upcoming';

    public function __construct(
        public readonly MinuteProgramItem $item,
        public readonly DateTimeImmutable $plannedStart, // ohne Verzögerung
        public readonly DateTimeImmutable $currentStart,  // mit aktueller Verzögerung
        public readonly bool $isCurrent,
        public readonly bool $isNext,
        // Start including a running overrun of the current item (live
        // tracking, see MinuteProgramDetailBuilder). Equals $currentStart
        // for past/current items and when nothing overruns.
        public readonly ?DateTimeImmutable $projectedStart = null,
        // Actual start as recorded via "Start" at FOH (only the live item).
        public readonly ?DateTimeImmutable $actualStart = null,
        public readonly string $status = self::STATUS_UPCOMING,
        public readonly int $position = 0,
    ) {
    }

    public function durationSeconds(): ?int
    {
        return $this->item->durationMinutes !== null ? $this->item->durationMinutes * 60 : null;
    }

    public function plannedEnd(): ?DateTimeImmutable
    {
        return $this->item->durationMinutes !== null ? $this->plannedStart->modify('+' . $this->item->durationMinutes . ' minutes') : null;
    }

    /** Effective start: actual start if recorded, otherwise the projected start. */
    public function effectiveStart(): DateTimeImmutable
    {
        return $this->actualStart ?? $this->projectedStart ?? $this->currentStart;
    }

    public function effectiveEnd(): ?DateTimeImmutable
    {
        return $this->item->durationMinutes !== null ? $this->effectiveStart()->modify('+' . $this->item->durationMinutes . ' minutes') : null;
    }
}
