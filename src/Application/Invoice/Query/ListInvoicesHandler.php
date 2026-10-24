<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Invoice\InvoiceRepositoryInterface;

final class ListInvoicesHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListInvoices);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $invoices = $this->invoices->search($query->searchTerm, $query->status, $query->pageSize, $offset);
        $totalCount = $this->invoices->countSearch($query->searchTerm, $query->status);

        $entries = array_map(
            fn ($invoice) => new InvoiceListEntry($invoice, $invoice->customerId !== null ? $this->customers->findById($invoice->customerId) : null),
            $invoices
        );

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
