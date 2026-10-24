<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Application\Query\QueryInterface;

final class GetUser implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
