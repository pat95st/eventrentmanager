<?php

declare(strict_types=1);

namespace App\Application\Tour\Query;

use App\Application\Query\QueryInterface;

final class GetTourOverview implements QueryInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
