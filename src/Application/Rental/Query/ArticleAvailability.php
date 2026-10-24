<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

/**
 * Verfügbarkeit eines Artikels über einen Zeitraum (z. B. einen Monat) für
 * den Ampel-Verfügbarkeitskalender auf der Artikel-Detailseite.
 */
final class ArticleAvailability
{
    /** @param array<string, int> $reservedByDay 'Y-m-d' => Summe der an diesem Tag verplanten Menge */
    public function __construct(
        public readonly int $totalInventory,
        public readonly array $reservedByDay,
    ) {
    }

    /** 0 = frei, 1 = teilweise vermietet, 2 = ausgebucht, 3 = keine Inventurnummern vorhanden. */
    public function statusForDay(string $day): int
    {
        if ($this->totalInventory === 0) {
            return 3;
        }

        $reserved = $this->reservedByDay[$day] ?? 0;

        if ($reserved <= 0) {
            return 0;
        }

        return $reserved >= $this->totalInventory ? 2 : 1;
    }
}
