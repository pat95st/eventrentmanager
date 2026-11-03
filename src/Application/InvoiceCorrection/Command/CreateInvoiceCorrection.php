<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;

/** Legt eine Rechnungskorrektur als Entwurf an; liefert die neue ID. */
final class CreateInvoiceCorrection implements CommandInterface
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly InvoiceCorrectionType $type,
        public readonly InvoiceCorrectionInput $input,
    ) {
    }
}
