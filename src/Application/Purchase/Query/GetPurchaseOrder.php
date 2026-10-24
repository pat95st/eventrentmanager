<?php

declare(strict_types=1);

namespace App\Application\Purchase\Query;

use App\Application\Query\QueryInterface;

final class GetPurchaseOrder implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
