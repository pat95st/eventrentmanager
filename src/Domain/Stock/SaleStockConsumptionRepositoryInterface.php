<?php

declare(strict_types=1);

namespace App\Domain\Stock;

interface SaleStockConsumptionRepositoryInterface
{
    public function save(SaleStockConsumption $consumption): void;

    /** @return SaleStockConsumption[] */
    public function findBySale(string $saleId): array;
}
