<?php

declare(strict_types=1);

namespace App\Application\Stock;

use App\Domain\Stock\SaleStockLot;
use InvalidArgumentException;

/**
 * Reine Verbrauchslogik ohne DB-Zugriff (analog zu InventoryNumberGenerator/
 * VoucherCodeGenerator/WeightConverter): verteilt eine zu verkaufende Menge
 * auf bereits geladene, FIFO-sortierte Chargen (älteste zuerst). Die
 * aufrufende Stelle (SellArticleFromStockHandler) ist dafür verantwortlich,
 * die mutierten $quantityRemaining-Werte der betroffenen Chargen sowie die
 * zurückgelieferten Allocation-Zeilen zu speichern.
 */
final class StockFifoAllocator
{
    /**
     * @param SaleStockLot[] $lotsFifo bereits nach receivedAt aufsteigend sortiert, nur mit quantityRemaining > 0
     * @return StockFifoAllocation
     */
    public static function allocate(array $lotsFifo, int $quantity): StockFifoAllocation
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Die zu verkaufende Menge muss mindestens 1 sein.');
        }

        $remainingToAllocate = $quantity;
        $entries = [];
        $totalCost = 0.0;

        foreach ($lotsFifo as $lot) {
            if ($remainingToAllocate <= 0) {
                break;
            }

            if ($lot->quantityRemaining <= 0) {
                continue;
            }

            $takeFromLot = min($lot->quantityRemaining, $remainingToAllocate);
            $entries[] = new StockFifoAllocationEntry($lot, $takeFromLot);
            $totalCost += $takeFromLot * (float) $lot->unitPurchasePrice;
            $remainingToAllocate -= $takeFromLot;
        }

        if ($remainingToAllocate > 0) {
            throw new InsufficientStockException($quantity - $remainingToAllocate, $quantity);
        }

        return new StockFifoAllocation($entries, number_format($totalCost, 2, '.', ''));
    }
}
