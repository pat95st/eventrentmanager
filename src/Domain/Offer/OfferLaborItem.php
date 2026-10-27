<?php

declare(strict_types=1);

namespace App\Domain\Offer;

final class OfferLaborItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $offerId,
        public readonly string $laborTimeArticleId,
        // Mitarbeiter/Benutzer, der diese Stunden geleistet hat; optional (NULL =
        // kein bestimmter Mitarbeiter zugeordnet, z. B. bei Altdaten). Mehrere
        // Mitarbeiter an derselben Veranstaltung ergeben sich einfach aus
        // mehreren Arbeitszeit-Positionen mit unterschiedlichem userId.
        public ?string $userId,
        public string $hours,            // Dezimalzahl als String, z. B. "2.50"
        public string $unitRatePerHour,  // beim Hinzufügen gesnapshoteter Stundensatz, siehe LaborTimeArticle
    ) {
    }

    public function lineTotal(): float
    {
        return (float) $this->hours * (float) $this->unitRatePerHour;
    }
}
