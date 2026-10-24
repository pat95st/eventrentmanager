<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use DateTimeImmutable;

/**
 * Eine Charge im reinen Verkaufsbestand eines Artikels (Exemplare OHNE
 * eigene Inventurnummer, die nie in den Vermietpool gelangen — siehe
 * App\Domain\Purchase\PurchaseTargetType::SaleStock). Entsteht beim
 * Wareneingang einer Bestellposition (ReceivePurchaseOrderItemHandler) oder
 * — bei $purchaseOrderItemId === null — durch eine manuelle Bestandskorrektur.
 *
 * Mehrere Chargen desselben Artikels können unterschiedliche Einkaufspreise
 * haben (z. B. bei einer späteren Nachbestellung zu anderen Konditionen).
 * Beim Verkauf werden Chargen in FIFO-Reihenfolge (älteste zuerst, siehe
 * $receivedAt) verbraucht, damit sich die tatsächliche Marge je Verkauf
 * exakt berechnen lässt (siehe App\Application\Stock\StockFifoAllocator).
 */
final class SaleStockLot
{
    public function __construct(
        public readonly string $id,
        public readonly string $articleId,
        public readonly ?string $purchaseOrderItemId,
        public readonly string $unitPurchasePrice,
        public readonly int $quantityReceived,
        public int $quantityRemaining,
        public readonly DateTimeImmutable $receivedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
