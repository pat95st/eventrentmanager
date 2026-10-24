<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Ein Eintrag der Preisstaffelung: Aufschlag in % auf den Tagesmietsatz
 * eines Artikels für eine bestimmte Anzahl Einsatztage. Tag 1 ist per
 * Definition immer 100 % (siehe UpdatePriceTiersHandler). Die Anzahl der
 * Staffel-Tage ist über die Oberfläche einstellbar (siehe
 * PriceTierSettingsController), begrenzt durch MAX_DAY_COUNT.
 */
final class PriceTier
{
    /** Obergrenze für die Anzahl konfigurierbarer Staffel-Tage (rein technische Absicherung gegen unbegrenztes Wachstum, keine fachliche Vorgabe). */
    public const MAX_DAY_COUNT = 60;

    public function __construct(
        public readonly int $dayCount,
        public string $percentage, // als String geführt, um Rundungsfehler bei DECIMAL zu vermeiden (siehe Article::$purchasePrice)
    ) {
    }
}
