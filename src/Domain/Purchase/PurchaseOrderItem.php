<?php

declare(strict_types=1);

namespace App\Domain\Purchase;

/**
 * Eine Position (Artikel + Menge) innerhalb einer Bestellung. $receivedQuantity
 * ist die Summe aller bislang erfassten Wareneingänge für diese Position
 * (Teillieferungen möglich, siehe ReceivePurchaseOrderItemHandler) — bleibt
 * 0, solange nichts eingetroffen ist. $unitPrice ist der vereinbarte/
 * erwartete Einkaufspreis je Einheit; bei Wareneingang in den Vermietpool
 * wird dieser als InventoryNumber::$purchasePriceOverride übernommen, bei
 * Wareneingang in den Verkaufsbestand als SaleStockLot::$unitPurchasePrice.
 */
final class PurchaseOrderItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $purchaseOrderId,
        public readonly string $articleId,
        public readonly PurchaseTargetType $targetType,
        public int $orderedQuantity,
        public string $unitPrice,
        public int $receivedQuantity = 0,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->orderedQuantity * (float) $this->unitPrice;
    }

    public function openQuantity(): int
    {
        return max(0, $this->orderedQuantity - $this->receivedQuantity);
    }

    public function isFullyReceived(): bool
    {
        return $this->receivedQuantity >= $this->orderedQuantity;
    }
}
