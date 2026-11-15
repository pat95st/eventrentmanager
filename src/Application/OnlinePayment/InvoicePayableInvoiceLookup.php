<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

use App\Application\Invoice\Query\GetInvoice;
use App\Application\Invoice\Query\GetInvoiceHandler;
use App\Domain\Exception\NotFoundException;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;

/**
 * Computes the amount due of an invoice the same way the invoice detail page
 * does: gross total (DocumentTotals) plus all issued (non-draft) invoice
 * corrections. A cancellation invoice therefore brings the amount to 0.
 */
final class InvoicePayableInvoiceLookup implements PayableInvoiceLookupInterface
{
    public function __construct(
        private readonly GetInvoiceHandler $getInvoiceHandler,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
        private readonly InvoiceCorrectionRepositoryInterface $corrections,
    ) {
    }

    public function find(string $invoiceId): ?PayableInvoice
    {
        try {
            $detail = $this->getInvoiceHandler->handle(new GetInvoice($invoiceId));
        } catch (NotFoundException) {
            return null;
        }

        $amount = $detail->totals($this->invoiceSettings->get())->grossTotal;
        foreach ($this->corrections->findByInvoice($invoiceId) as $correction) {
            if (!$correction->isDraft()) {
                $amount += $correction->grossTotal();
            }
        }

        $invoice = $detail->invoice;

        return new PayableInvoice(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoiceNumber,
            status: $invoice->status,
            amountDue: number_format(max(0.0, round($amount, 2)), 2, '.', ''),
            invoiceDate: $invoice->invoiceDate,
            dueDate: $invoice->dueDate,
            customerName: $detail->customer?->displayName(),
            paidAt: $invoice->paidAt,
        );
    }
}
