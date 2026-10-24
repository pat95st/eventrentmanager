<?php

declare(strict_types=1);

namespace App\Application\Sale\Query;

use App\Application\Query\QueryInterface;

final class ListSales implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
