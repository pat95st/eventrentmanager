<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

/**
 * Ein Programmpunkt eines Minutenprogramms. Die tatsächliche Uhrzeit wird
 * NICHT hier gespeichert (siehe MinuteProgram-Klassen-Doc), sondern von
 * App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder berechnet.
 */
final class MinuteProgramItem
{
    /** @param array<string, string> $tradeNotes */
    public function __construct(
        public readonly string $id,
        public readonly string $minuteProgramId,
        public int $sortOrder,
        public string $name,
        public ?string $description,
        public ?string $noteGeneral,
        // Migration 171: notes per trade, trade ID => note (replaces the
        // fixed sound/light/video/stage notes), see MinuteProgramTradeNotes.
        public array $tradeNotes,
        public ?int $durationMinutes,
        // Migration 166: no strobe lighting may be used during this item.
        public bool $noStrobe = false,
    ) {
    }
}
