<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

final class ConfirmBankImportResult
{
    /**
     * @param string[] $notFoundInvoiceNumbers Rechnungsnummern, zu denen keine Rechnung gefunden wurde
     * @param string[] $notPayableInvoiceNumbers Rechnungsnummern einer gefundenen, aber nicht
     *        zahlbaren Rechnung (bereits bezahlt/storniert/noch nicht versendet)
     */
    public function __construct(
        public readonly int $markedPaidCount,
        public readonly array $notFoundInvoiceNumbers,
        public readonly array $notPayableInvoiceNumbers,
    ) {
    }
}
