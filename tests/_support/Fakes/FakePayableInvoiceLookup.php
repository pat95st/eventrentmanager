<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\OnlinePayment\PayableInvoice;
use App\Application\OnlinePayment\PayableInvoiceLookupInterface;
use App\Domain\Invoice\InvoiceRepositoryInterface;

/** Builds the PayableInvoice from the (in-memory) invoice header plus a fixed amount per invoice. */
final class FakePayableInvoiceLookup implements PayableInvoiceLookupInterface
{
    /** @var array<string, string> invoiceId => amount due */
    public array $amounts = [];

    public function __construct(private readonly InvoiceRepositoryInterface $invoices)
    {
    }

    public function find(string $invoiceId): ?PayableInvoice
    {
        $invoice = $this->invoices->findById($invoiceId);
        if ($invoice === null) {
            return null;
        }

        return new PayableInvoice(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoiceNumber,
            status: $invoice->status,
            amountDue: $this->amounts[$invoiceId] ?? '0.00',
            invoiceDate: $invoice->invoiceDate,
            dueDate: $invoice->dueDate,
            customerName: null,
            paidAt: $invoice->paidAt,
        );
    }
}
