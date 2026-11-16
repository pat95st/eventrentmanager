<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class AddMinuteProgramItem implements CommandInterface
{
    /** @param array<string, string> $tradeNotes */
    public function __construct(
        public readonly string $minuteProgramId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $noteGeneral,
        // trade ID => note (migration 171)
        public readonly array $tradeNotes,
        public readonly ?int $durationMinutes,
        // Migration 166: no strobe lighting may be used during this item.
        public readonly bool $noStrobe = false,
    ) {
    }
}
