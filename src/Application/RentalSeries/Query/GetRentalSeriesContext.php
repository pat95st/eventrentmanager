<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\Query\QueryInterface;

/** Position of a rental within its series (for the rental detail page); null when not in a series. */
final class GetRentalSeriesContext implements QueryInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
