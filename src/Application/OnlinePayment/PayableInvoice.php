<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

use App\Domain\Invoice\InvoiceStatus;
use DateTimeImmutable;

/** The data of an invoice the public payment page and the checkout need. */
final class PayableInvoice
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly ?string $invoiceNumber,
        public readonly InvoiceStatus $status,
        // Gross total including issued invoice corrections, "123.45".
        public readonly string $amountDue,
        public readonly ?DateTimeImmutable $invoiceDate,
        public readonly ?DateTimeImmutable $dueDate,
        public readonly ?string $customerName,
        public readonly ?DateTimeImmutable $paidAt,
    ) {
    }

    /** Only sent invoices with a positive open amount can be paid online. */
    public function isPayable(): bool
    {
        return $this->status === InvoiceStatus::Sent && (float) $this->amountDue >= 0.01;
    }
}
