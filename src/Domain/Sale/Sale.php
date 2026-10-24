<?php

declare(strict_types=1);

namespace App\Domain\Sale;

use DateTimeImmutable;

/**
 * Ein Verkauf — entweder eines einzelnen, konkreten Exemplars (Inventarnummer)
 * aus dem Vermietpool (gebrauchter Artikel, $inventoryNumberId gesetzt,
 * $quantity dann immer 1) ODER einer Menge aus dem reinen Verkaufsbestand
 * (neue Ware ohne eigene Inventurnummer, $inventoryNumberId === null, siehe
 * App\Domain\Stock\SaleStockLot).
 *
 * Bei Verkauf aus dem Vermietpool existiert je Inventarnummer höchstens ein
 * Sale-Datensatz (UNIQUE auf inventory_number_id, siehe Migration 072).
 * Einmal verkauft, bleibt die Inventarnummer selbst dauerhaft erhalten (kein
 * DELETE mehr möglich, siehe DeleteInventoryNumberHandler) und wird vom
 * InventoryNumberGenerator nie wieder vergeben.
 *
 * $costOfGoodsSold ist der zum Verkaufszeitpunkt gesnapshotete Einkaufspreis
 * ALLER verkauften Einheiten (bei Vermietpool-Verkauf: effectivePurchasePrice()
 * der Inventarnummer; bei Bestandsverkauf: Summe der laut FIFO verbrauchten
 * Chargen, siehe App\Application\Stock\StockFifoAllocator) — für die
 * Margenberechnung in der Verkaufsübersicht. Kann null sein (z. B. wenn beim
 * Verkauf kein Einkaufspreis bekannt war).
 */
final class Sale
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $inventoryNumberId,
        public readonly string $articleId,
        public readonly int $quantity,
        public readonly ?string $customerId, // optional: Barverkauf ohne hinterlegten Kunden
        public readonly string $invoiceId, // die beim Verkauf automatisch angelegte Rechnung (Entwurf)
        public readonly string $salePrice, // Gesamt-Verkaufspreis (netto) aller $quantity Einheiten
        public readonly ?string $costOfGoodsSold,
        public readonly ?string $shippingAmount, // optionaler, zusätzlich abgerechneter Versandkostenbetrag
        public readonly DateTimeImmutable $soldAt,
        public readonly ?string $note,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function isFromStock(): bool
    {
        return $this->inventoryNumberId === null;
    }

    /** Marge (Verkaufspreis - Einkaufspreis), oder null, falls kein Einkaufspreis bekannt ist. */
    public function marginAmount(): ?float
    {
        if ($this->costOfGoodsSold === null) {
            return null;
        }

        return ((float) $this->salePrice) - ((float) $this->costOfGoodsSold);
    }
}
