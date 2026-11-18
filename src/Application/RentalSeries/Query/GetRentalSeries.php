<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\Query\QueryInterface;

/** Series overview: all occurrences with status and warnings. */
final class GetRentalSeries implements QueryInterface
{
    public function __construct(public readonly string $seriesId)
    {
    }
}
