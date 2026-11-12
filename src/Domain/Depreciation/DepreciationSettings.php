<?php

declare(strict_types=1);

namespace App\Domain\Depreciation;

/**
 * Firmenweite Regeln der linearen Abschreibung (AfA), Singleton-Zeile
 * analog zu OverageSettings (Migration 146). Beträge als String, um
 * Rundungsfehler bei DECIMAL zu vermeiden.
 */
final class DepreciationSettings
{
    public const MAX_USEFUL_LIFE_YEARS = 50;

    public function __construct(
        public int $defaultUsefulLifeYears,
        public string $lowValueThreshold, // GWG-Grenze: bis zu diesem Einkaufspreis Sofortabschreibung im Anschaffungsjahr; "0.00" = aus
        public DepreciationFirstYearMode $firstYearMode,
        public string $residualValue, // Erinnerungswert, der nach Ablauf der Nutzungsdauer stehen bleibt (z. B. 1 €)
    ) {
    }

    public static function defaults(): self
    {
        return new self(5, '800.00', DepreciationFirstYearMode::Monthly, '0.00');
    }
}
