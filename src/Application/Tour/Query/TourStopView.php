<?php

declare(strict_types=1);

namespace App\Application\Tour\Query;

use App\Application\RentalSeries\MaterialConflict;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\TourStopItem;
use App\Domain\Tour\TourStopTechnician;

/** One stop or waypoint of the tour page. */
final class TourStopView
{
    /**
     * @param array<string, int> $material article id => quantity needed (stops only)
     * @param array<string, TourStopItem> $deviations article id => deviation
     * @param list<array{articleId: string, before: int, after: int}> $changes compared to the previous stop
     * @param list<array{userId: string, name: string, role: ?string, pending: bool, deviation: ?TourStopTechnician}> $crew
     * @param TourStopTechnician[] $crewDeviations
     * @param MaterialConflict[] $conflicts
     * @param list<array{id: string, name: string}> $minutePrograms
     * @param string[] $warnings
     */
    public function __construct(
        public readonly TourStop $stop,
        public readonly ?int $number,
        public readonly bool $locked,
        public readonly array $material = [],
        public readonly array $deviations = [],
        public readonly array $changes = [],
        public readonly array $crew = [],
        public readonly array $crewDeviations = [],
        public readonly array $conflicts = [],
        public readonly array $minutePrograms = [],
        public readonly array $warnings = [],
    ) {
    }
}
