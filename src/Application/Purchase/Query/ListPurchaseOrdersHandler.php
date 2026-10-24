<?php

declare(strict_types=1);

namespace App\Application\Purchase\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;

final class ListPurchaseOrdersHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SupplierRepositoryInterface $suppliers,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListPurchaseOrders);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $orders = $this->purchaseOrders->search($query->searchTerm, $query->status, $query->pageSize, $offset);
        $totalCount = $this->purchaseOrders->countSearch($query->searchTerm, $query->status);

        $entries = array_map(
            fn ($order) => new PurchaseOrderListEntry(
                $order,
                $this->suppliers->findById($order->supplierId),
                count($this->purchaseOrders->findItemsByOrder($order->id)),
            ),
            $orders
        );

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
