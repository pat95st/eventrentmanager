<?php

declare(strict_types=1);

namespace App\Domain\InvoiceCorrection;

/** Eine Position einer Rechnungskorrektur; Beträge netto, negativ = Gutschrift. */
final class InvoiceCorrectionLine
{
    public function __construct(
        public readonly string $id,
        public readonly string $correctionId,
        public int $sortOrder,
        public string $description,
        public string $quantity,
        public string $unitPrice,
    ) {
    }

    public function lineTotal(): float
    {
        return round((float) $this->quantity * (float) $this->unitPrice, 2);
    }
}
