<?php

declare(strict_types=1);

namespace App\Domain\Offer;

/**
 * Eine Position (Artikel + Menge) innerhalb eines Angebots. unitPricePerDay
 * ist trotz des Namens (siehe Migration/Schema) der END-Preis je Einheit für
 * den GESAMTEN Angebotszeitraum: er wird beim Anlegen/Bearbeiten per
 * RentalPriceCalculator aus Article::$dailyRentalPrice und der
 * Preisstaffelung als Vorschlag vorbefüllt, kann aber vom Benutzer frei
 * überschrieben werden (z. B. für einen Rabatt) — siehe offers/form.php.
 * Zeilensumme = unitPricePerDay * quantity.
 */
final class OfferItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $offerId,
        public readonly string $articleId,
        public int $quantity,
        public string $unitPricePerDay, // als String geführt, um Rundungsfehler bei DECIMAL zu vermeiden (siehe Article::$dailyRentalPrice)
        public ?string $lineNote,
    ) {
    }

    public function lineTotal(): float
    {
        return ((float) $this->unitPricePerDay) * $this->quantity;
    }
}
