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
    public function __construct(
        public readonly string $id,
        public readonly string $minuteProgramId,
        public int $sortOrder,
        public string $name,
        public ?string $description,
        public ?string $noteGeneral,
        public ?string $noteSound,
        public ?string $noteLight,
        public ?string $noteVideo,
        public ?string $noteStage,
        public ?int $durationMinutes,
    ) {
    }
}
