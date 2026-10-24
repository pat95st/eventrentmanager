<?php

declare(strict_types=1);

namespace App\Application\Receipt\Query;

use App\Domain\Receipt\Receipt;
use App\Domain\Supplier\Supplier;

final class ReceiptDetail
{
    /** @param ReceiptInventoryNumberEntry[] $inventoryNumbers */
    public function __construct(
        public readonly Receipt $receipt,
        public readonly ?Supplier $supplier,
        public readonly array $inventoryNumbers = [],
    ) {
    }
}
