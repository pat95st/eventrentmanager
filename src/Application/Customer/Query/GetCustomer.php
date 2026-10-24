<?php

declare(strict_types=1);

namespace App\Application\Customer\Query;

use App\Application\Query\QueryInterface;

final class GetCustomer implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
