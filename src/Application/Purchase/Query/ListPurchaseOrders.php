<?php

declare(strict_types=1);

namespace App\Application\Purchase\Query;

use App\Application\Query\QueryInterface;
use App\Domain\Purchase\PurchaseOrderStatus;

final class ListPurchaseOrders implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null,
        public readonly ?PurchaseOrderStatus $status = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
