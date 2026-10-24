<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Query\QueryInterface;

final class ListPasskeysForUser implements QueryInterface
{
    public function __construct(public readonly string $userId)
    {
    }
}
