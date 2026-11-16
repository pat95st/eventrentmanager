<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class UpdateMinuteProgramItem implements CommandInterface
{
    /** @param array<string, string> $tradeNotes */
    public function __construct(
        public readonly string $itemId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $noteGeneral,
        // submitted notes, trade ID => note (migration 172); notes of trades not submitted are kept
        public readonly array $tradeNotes,
        public readonly ?int $durationMinutes,
        // Migration 166: no strobe lighting may be used during this item.
        public readonly bool $noStrobe = false,
    ) {
    }
}
