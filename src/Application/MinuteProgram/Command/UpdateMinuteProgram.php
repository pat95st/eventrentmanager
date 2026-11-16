<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class UpdateMinuteProgram implements CommandInterface
{
    /** @param list<string>|null $tradeIds */
    public function __construct(
        public readonly string $minuteProgramId,
        public readonly string $name,
        public readonly ?string $room,
        public readonly string $startAt,
        public readonly bool $jingleBefore,
        public readonly int $handheldMicCount,
        public readonly int $headsetMicCount,
        public readonly ?string $jingleAudioFileId = null,
        // Migration 172: only applied when $setTrades is true; null = automatic.
        public readonly bool $setTrades = false,
        public readonly ?array $tradeIds = null,
    ) {
    }
}
