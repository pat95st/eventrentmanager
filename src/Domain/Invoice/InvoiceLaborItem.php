<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

final class InvoiceLaborItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public readonly string $laborTimeArticleId,
        public string $hours,            // Dezimalzahl als String, z. B. "2.50"
        public string $unitRatePerHour,  // beim Hinzufügen gesnapshoteter Stundensatz, siehe LaborTimeArticle
    ) {
    }

    public function lineTotal(): float
    {
        return (float) $this->hours * (float) $this->unitRatePerHour;
    }
}
