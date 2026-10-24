<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Application\Query\QueryInterface;

final class ListCaseItems implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null,
        public readonly ?string $manufacturerId = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
