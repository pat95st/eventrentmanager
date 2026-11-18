<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Domain\Rental\Rental;
use App\Domain\RentalSeries\RentalSeries;

final class RentalSeriesContext
{
    /** @param Rental[] $following occurrences after this one */
    public function __construct(
        public readonly RentalSeries $series,
        public readonly int $position, // 1-based
        public readonly int $total,
        public readonly ?Rental $previous,
        public readonly array $following,
    ) {
    }
}
