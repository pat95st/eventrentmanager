<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\RentalSeries\SeriesPreviewRow;
use App\Domain\Rental\Rental;
use App\Domain\RentalSeries\RecurrenceRule;

final class RentalSeriesPreview
{
    /** @param SeriesPreviewRow[] $rows occurrences after the source rental */
    public function __construct(
        public readonly Rental $source,
        public readonly RecurrenceRule $rule,
        public readonly array $rows,
    ) {
    }
}
