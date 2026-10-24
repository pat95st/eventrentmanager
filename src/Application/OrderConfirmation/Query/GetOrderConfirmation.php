<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Application\Query\QueryInterface;

final class GetOrderConfirmation implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
