<?php

declare(strict_types=1);

namespace App\Application\Search\Query;

use App\Application\Query\QueryInterface;
use App\Domain\User\User;

final class GlobalSearch implements QueryInterface
{
    public function __construct(
        public readonly string $term,
        public readonly User $user,
        public readonly int $limitPerType = 5,
    ) {
    }
}
