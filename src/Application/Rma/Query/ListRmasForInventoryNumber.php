<?php

declare(strict_types=1);

namespace App\Application\Rma\Query;

use App\Application\Query\QueryInterface;

final class ListRmasForInventoryNumber implements QueryInterface
{
    public function __construct(public readonly string $inventoryNumberId)
    {
    }
}
