<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/** Eine reine Zwischenüberschrift ohne Preis — siehe InvoiceFreeTextItem für das gemeinsame orderedRows()-Konzept. */
final class InvoiceSectionText
{
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public string $heading,
        public int $sortOrder = 0,
    ) {
    }
}
