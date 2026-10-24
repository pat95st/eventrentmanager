<?php

declare(strict_types=1);

namespace App\Domain\Offer;

/**
 * Eine Verkaufsposition innerhalb eines Angebots — ein Angebot kann sowohl
 * Mietpositionen (OfferItem) als auch Verkaufspositionen (diese Klasse)
 * enthalten (siehe Produktentscheidung "gemischt erlauben"). Anders als bei
 * OfferItem ist $unitPrice ein EINMALPREIS je Einheit, kein Tagespreis.
 *
 * $inventoryNumberId gesetzt: Verkauf eines konkreten, gebrauchten Exemplars
 * aus dem Vermietpool (quantity dann immer 1, wie bei SellInventoryNumber).
 * $inventoryNumberId === null: Verkauf aus dem reinen Verkaufsbestand (neue
 * Ware, beliebige Menge, siehe SellArticleFromStock).
 *
 * $fulfilledSaleId wird gesetzt, sobald diese Position tatsächlich über
 * CreateInvoiceFromOfferHandler abgerechnet wurde (verweist auf den dabei
 * entstandenen App\Domain\Sale\Sale-Datensatz) — ein Angebot, das bereits
 * abgerechnete Verkaufspositionen enthält, kann danach nicht mehr bearbeitet
 * werden (siehe UpdateOfferHandler), damit eine erneute Bearbeitung nicht
 * versehentlich einen bereits abgerechneten Verkauf verwaist.
 */
final class OfferSaleItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $offerId,
        public readonly string $articleId,
        public readonly ?string $inventoryNumberId,
        public int $quantity,
        public string $unitPrice,
        public ?string $lineNote,
        public ?string $fulfilledSaleId = null,
    ) {
    }

    public function lineTotal(): float
    {
        return ((float) $this->unitPrice) * $this->quantity;
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilledSaleId !== null;
    }
}
