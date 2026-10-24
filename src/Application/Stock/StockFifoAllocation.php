<?php

declare(strict_types=1);

namespace App\Application\Stock;

final class StockFifoAllocation
{
    /** @param StockFifoAllocationEntry[] $entries */
    public function __construct(
        public readonly array $entries,
        public readonly string $totalCost,
    ) {
    }
}
