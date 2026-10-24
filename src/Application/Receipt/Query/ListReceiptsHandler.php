<?php

declare(strict_types=1);

namespace App\Application\Receipt\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;

final class ListReceiptsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly SupplierRepositoryInterface $suppliers,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListReceipts);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $receipts = $this->receipts->search(
            $query->searchTerm,
            $query->supplierId,
            $query->status,
            $query->category,
            $query->pageSize,
            $offset,
        );
        $totalCount = $this->receipts->countSearch($query->searchTerm, $query->supplierId, $query->status, $query->category);

        $entries = array_map(
            fn ($receipt) => new ReceiptListEntry($receipt, $this->suppliers->findById($receipt->supplierId)),
            $receipts
        );

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
