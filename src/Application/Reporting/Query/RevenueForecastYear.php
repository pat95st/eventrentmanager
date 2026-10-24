<?php

declare(strict_types=1);

namespace App\Application\Reporting\Query;

/**
 * Erwarteter Umsatz eines einzelnen Kalenderjahres, aufgeschlüsselt nach
 * Herkunft (siehe GetRevenueForecastHandler für die Zuordnungsregeln und
 * die Vermeidung von Doppelzählung).
 */
final class RevenueForecastYear
{
    public function __construct(
        public readonly int $year,
        // Offene (nicht abgelehnte) Angebote, die noch nicht in eine
        // Auftragsbestätigung oder Rechnung übernommen wurden.
        public readonly float $offerAmount,
        // Nicht stornierte Auftragsbestätigungen.
        public readonly float $orderConfirmationAmount,
        // Nicht stornierte Rechnungen (unabhängig vom Zahlungsstatus).
        public readonly float $invoiceAmount,
    ) {
    }

    public function total(): float
    {
        return $this->offerAmount + $this->orderConfirmationAmount + $this->invoiceAmount;
    }
}
