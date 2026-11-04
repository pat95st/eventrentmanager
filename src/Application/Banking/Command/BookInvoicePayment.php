<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandInterface;

/** Rechnungszahlung auf dem Konto verbuchen; die Rechnung wird als bezahlt markiert (Migration 131). */
final class BookInvoicePayment implements CommandInterface
{
    public function __construct(
        public readonly string $bankAccountId,
        public readonly string $invoiceId,
        public readonly string $bookingDate, // 'Y-m-d'
        public readonly string $amount,      // gezahlter Betrag
    ) {
    }
}
