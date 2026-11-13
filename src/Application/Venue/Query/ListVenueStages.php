<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryInterface;

final class ListVenueStages implements QueryInterface
{
    public function __construct(public readonly string $venueId)
    {
    }
}
