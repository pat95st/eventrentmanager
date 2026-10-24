<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Application\Query\QueryInterface;

final class GetInventoryNumberContext implements QueryInterface
{
    public function __construct(public readonly string $inventoryNumberId)
    {
    }
}
