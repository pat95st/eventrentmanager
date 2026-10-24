<?php

declare(strict_types=1);

namespace App\Application\Purchase\Query;

use App\Domain\Purchase\PurchaseOrder;
use App\Domain\Supplier\Supplier;

final class PurchaseOrderDetail
{
    /** @param PurchaseOrderItemWithArticle[] $items */
    public function __construct(
        public readonly PurchaseOrder $order,
        public readonly ?Supplier $supplier,
        public readonly array $items,
    ) {
    }

    public function totalValue(): float
    {
        $total = 0.0;
        foreach ($this->items as $entry) {
            $total += $entry->item->lineTotal();
        }

        return $total;
    }
}
