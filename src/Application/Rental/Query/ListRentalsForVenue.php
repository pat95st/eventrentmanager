<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryInterface;

final class ListRentalsForVenue implements QueryInterface
{
    public function __construct(public readonly string $venueId)
    {
    }
}
