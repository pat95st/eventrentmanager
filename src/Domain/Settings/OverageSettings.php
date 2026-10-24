<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Firmenweite Regelungen für das Überziehen der Mietzeit, Singleton-Zeile
 * analog zu PricingSettings/InvoiceSettings.
 *
 * Die fachliche Regel "überzogene Einsatztage werden zum vollen Tagespreis
 * (100 %, Tag 1 der Preisstaffel — siehe App\Domain\Settings\PriceTier)
 * OHNE Rabattierung berechnet" ist fest vorgegeben und braucht daher kein
 * eigenes Feld: die Preisstaffel selbst definiert Tag 1 bereits als 100 %,
 * sodass für einen einzelnen überzogenen Tag ganz automatisch keine
 * Rabattierung greift, solange er als eigenständiger Ein-Tages-Posten
 * berechnet wird (nicht als Verlängerung des ursprünglichen, ggf.
 * rabattierten Zeitraums). Konfigurierbar ist hier ausschließlich die
 * zusätzliche Überzugspauschale (fester Betrag, unabhängig von der Anzahl
 * überzogener Tage).
 */
final class OverageSettings
{
    public function __construct(
        public bool $flatFeeEnabled,
        public string $flatFeeAmount, // als String geführt, um Rundungsfehler bei DECIMAL zu vermeiden (siehe PriceTier::$percentage)
    ) {
    }
}
