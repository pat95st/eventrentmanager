<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Query;

use App\Domain\InvoiceCorrection\InvoiceCorrection;

final class InvoiceCorrectionListEntry
{
    public function __construct(
        public readonly InvoiceCorrection $correction,
        public readonly ?string $invoiceNumber,
        public readonly ?string $customerName,
    ) {
    }
}
