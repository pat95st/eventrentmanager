<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;

final class ListInvoiceCorrectionsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InvoiceCorrectionRepositoryInterface $corrections,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    /** @return InvoiceCorrectionListEntry[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListInvoiceCorrections);

        $list = $query->invoiceId !== null
            ? $this->corrections->findByInvoice($query->invoiceId)
            : $this->corrections->findAll($query->search);

        $invoices = [];
        $customerNames = [];
        $entries = [];
        foreach ($list as $correction) {
            $invoice = $invoices[$correction->invoiceId] ??= $this->invoices->findById($correction->invoiceId);
            $customerName = null;
            if ($invoice?->customerId !== null) {
                $customerName = $customerNames[$invoice->customerId] ??= ($this->customers->findById($invoice->customerId)?->displayName() ?? '');
            }
            $entries[] = new InvoiceCorrectionListEntry($correction, $invoice?->invoiceNumber, $customerName !== '' ? $customerName : null);
        }

        return $entries;
    }
}
