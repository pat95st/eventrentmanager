<?php

declare(strict_types=1);

namespace App\Application\Stock;

use App\Domain\Stock\SaleStockLot;

final class StockFifoAllocationEntry
{
    public function __construct(
        public readonly SaleStockLot $lot,
        public readonly int $quantity,
    ) {
    }
}
