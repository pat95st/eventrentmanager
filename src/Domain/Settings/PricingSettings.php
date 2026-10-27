<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Firmenweite Preis-Einstellungen, die sowohl für Angebote als auch für
 * Rechnungen gelten (bewusst eine EIGENE Settings-Gruppe, getrennt von
 * InvoiceSettings — jene ist laut Klassen-Doc ausdrücklich "für die
 * Rechnungsfunktion", während die Zubehörpauschale auch auf Angebote wirkt).
 * Singleton-Zeile analog zu InvoiceSettings/CompanySettings.
 *
 * Der hier hinterlegte Wert ist NICHT das, was auf einem konkreten
 * Angebot/einer Rechnung steht — beim Anlegen (bzw. beim erneuten Aktivieren
 * der Pauschale) wird der aktuelle Wert auf das Dokument SELBST gesnapshotet
 * (siehe Invoice::$accessoryFlatRatePercent / Offer::$accessoryFlatRatePercent),
 * damit eine spätere Änderung dieser Einstellung bereits angelegte
 * Dokumente nie rückwirkend verändert.
 */
final class PricingSettings
{
    public function __construct(
        public string $accessoryFlatRatePercent, // als String geführt, um Rundungsfehler bei DECIMAL zu vermeiden (siehe InvoiceSettings::$vatRatePercent)
        // Globale Rundung der aus der Preisstaffelung berechneten Mietpreise
        // (siehe RentalPriceCalculator::calculate(), PriceRoundingStep/
        // PriceRoundingDirection) — standardmäßig deaktiviert, damit sich am
        // Verhalten bestehender Installationen ohne explizite Einstellung
        // nichts ändert.
        public PriceRoundingStep $priceRoundingStep = PriceRoundingStep::None,
        public PriceRoundingDirection $priceRoundingDirection = PriceRoundingDirection::Nearest,
    ) {
    }
}
