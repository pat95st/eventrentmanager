<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Query\QueryInterface;

final class ListImpersonations implements QueryInterface
{
    public function __construct(
        public readonly ?int $page = null,
        public readonly ?int $pageSize = null,
    ) {
    }
}
