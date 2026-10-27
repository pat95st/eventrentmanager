<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use App\Domain\LaborTime\LaborTimeBillingUnit;

final class InvoiceLaborItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public readonly string $laborTimeArticleId,
        // Mitarbeiter/Benutzer, der diese Stunden geleistet hat; optional (NULL =
        // kein bestimmter Mitarbeiter zugeordnet, z. B. bei Altdaten). Mehrere
        // Mitarbeiter an derselben Veranstaltung ergeben sich einfach aus
        // mehreren Arbeitszeit-Positionen mit unterschiedlichem userId.
        public ?string $userId,
        // Abgerechnete Menge, Dezimalzahl als String, z. B. "2.50" — bedeutet
        // Stunden bei billingUnit === Hour, Tage bei billingUnit === Day.
        public string $hours,
        // Beim Hinzufügen gesnapshoteter Satz, siehe LaborTimeArticle — der
        // Feldname blieb aus Kompatibilität zum bestehenden Schema erhalten,
        // trägt bei billingUnit === Day aber den Tagessatz (nicht den Stundensatz).
        public string $unitRatePerHour,
        public LaborTimeBillingUnit $billingUnit = LaborTimeBillingUnit::Hour,
    ) {
    }

    public function lineTotal(): float
    {
        return (float) $this->hours * (float) $this->unitRatePerHour;
    }
}
