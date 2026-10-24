<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryInterface;

final class ListRentals implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null,
        public readonly ?string $customerId = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
