<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\RentalSeries\MaterialConflict;
use App\Domain\Rental\Rental;
use App\Domain\RentalSeries\RentalSeries;

final class RentalSeriesOverview
{
    /** @param list<array{rental: Rental, customer: string, started: bool, conflicts: MaterialConflict[]}> $occurrences */
    public function __construct(
        public readonly RentalSeries $series,
        public readonly array $occurrences,
    ) {
    }
}
