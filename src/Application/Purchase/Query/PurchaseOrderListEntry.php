<?php

declare(strict_types=1);

namespace App\Application\Purchase\Query;

use App\Domain\Purchase\PurchaseOrder;
use App\Domain\Supplier\Supplier;

final class PurchaseOrderListEntry
{
    public function __construct(
        public readonly PurchaseOrder $order,
        public readonly ?Supplier $supplier,
        public readonly int $itemCount,
    ) {
    }
}
