<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgram;
use DateTimeImmutable;

final class MinuteProgramDetail
{
    /**
     * @param MinuteProgramItemDetail[] $items
     * @param list<MinuteProgramTrade> $trades
     */
    public function __construct(
        public readonly MinuteProgram $program,
        public readonly array $items,
        public readonly ?DateTimeImmutable $computedAt = null,
        // Stored delay plus the current item's running overrun (live tracking).
        public readonly ?int $projectedDelaySeconds = null,
        // How far the current item has run past the next item's start.
        public readonly int $overrunSeconds = 0,
        public readonly bool $finished = false,
        // Migration 172: trades this program offers notes for (global order).
        public readonly array $trades = [],
    ) {
    }

    public function currentItem(): ?MinuteProgramItemDetail
    {
        foreach ($this->items as $item) {
            if ($item->isCurrent) {
                return $item;
            }
        }

        return null;
    }

    public function nextItem(): ?MinuteProgramItemDetail
    {
        foreach ($this->items as $item) {
            if ($item->isNext) {
                return $item;
            }
        }

        return null;
    }

    public function projectedDelay(): int
    {
        return $this->projectedDelaySeconds ?? $this->program->currentDelaySeconds;
    }
}
