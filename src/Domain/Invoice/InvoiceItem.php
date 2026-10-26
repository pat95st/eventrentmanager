<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * Eine Position (Artikel + Menge) innerhalb einer Rechnung — strukturell
 * identisch zu OfferItem (siehe dortiger Kommentar für die genaue Bedeutung
 * von unitPricePerDay). Zeilensumme = unitPricePerDay * quantity.
 */
final class InvoiceItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public readonly string $articleId,
        public int $quantity,
        public string $unitPricePerDay,
        public ?string $lineNote,
        // Gemeinsame Reihenfolge mit InvoiceFreeTextItem/InvoiceSectionText
        // (orderedRows()-Konzept, siehe InvoiceRow/InvoiceDetail::orderedRows()).
        public int $sortOrder = 0,
        // Mietposition (Standard) vs. Verkaufs-/Sonstige Position — nur für
        // die "nur Mietpositionen"-Regel des Pauschalpreises relevant (siehe
        // Invoice::$flatRateEnabled-Klassendoc/DocumentTotals): ist
        // $isRentalItem = false, zählt die Position NICHT zur
        // Mietpreis-Zwischensumme (bzw. zur Pauschale), sondern bleibt wie
        // bisher einzeln bepreist und fließt in DocumentTotals::$saleTotal.
        public bool $isRentalItem = true,
    ) {
    }

    public function lineTotal(): float
    {
        return ((float) $this->unitPricePerDay) * $this->quantity;
    }
}
