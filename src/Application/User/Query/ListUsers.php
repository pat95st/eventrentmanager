<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Application\Query\QueryInterface;

final class ListUsers implements QueryInterface
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
