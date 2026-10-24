<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * Ein frei formulierbarer Posten ohne Artikelbezug — strukturell identisch
 * zu App\Domain\Offer\OfferFreeTextItem, siehe dort für die ausführliche
 * Begründung des orderedRows()-Konzepts (App\Application\Invoice\Query\
 * InvoiceDetail::orderedRows()).
 */
final class InvoiceFreeTextItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public int $sortOrder = 0,
    ) {
    }

    public function lineTotal(): float
    {
        return ((float) $this->unitPrice) * ((float) $this->quantity);
    }
}
