<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandInterface;

/** Erstattung an den Kunden bzw. Nachzahlung des Kunden ist erfolgt. */
final class MarkInvoiceCorrectionSettled implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $settledAt,
    ) {
    }
}
