<?php

declare(strict_types=1);

namespace App\Domain\Offer;

/**
 * Ein frei formulierbarer Posten ohne Artikelbezug (z. B. eine Pauschale
 * oder ein Hinweistext mit Preis) — Teil des orderedRows()-Konzepts (siehe
 * App\Application\Offer\Query\OfferDetail::orderedRows()), zuerst für
 * App\Domain\OrderConfirmation eingeführt und hier auf Angebote übertragen:
 * reguläre Positionen (OfferItem), Freitext-Posten (diese Klasse) und
 * Zwischentexte (nur Überschriften, siehe OfferSectionText) teilen sich über
 * $sortOrder eine gemeinsame, frei wählbare Reihenfolge. Arbeitszeit-/
 * Verkaufspositionen (OfferLaborItem/OfferSaleItem) bleiben davon bewusst
 * unberührt — sie erscheinen weiterhin in eigenen, festen Abschnitten NACH
 * der Positionstabelle (siehe offers/show.php/OfferPdfGenerator).
 */
final class OfferFreeTextItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $offerId,
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public int $sortOrder = 0,
    ) {
    }

    public function lineTotal(): float
    {
        return ((float) $this->unitPrice) * ((float) $this->quantity);
    }
}
