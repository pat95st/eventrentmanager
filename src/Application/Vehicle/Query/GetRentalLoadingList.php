<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Query;

use App\Application\Query\QueryInterface;

final class GetRentalLoadingList implements QueryInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
