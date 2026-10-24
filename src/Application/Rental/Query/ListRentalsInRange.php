<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryInterface;

final class ListRentalsInRange implements QueryInterface
{
    public function __construct(
        public readonly string $from, // 'Y-m-d'
        public readonly string $to,   // 'Y-m-d'
    ) {
    }
}
