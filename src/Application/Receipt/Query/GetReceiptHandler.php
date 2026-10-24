<?php

declare(strict_types=1);

namespace App\Application\Receipt\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;

final class GetReceiptHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(QueryInterface $query): ReceiptDetail
    {
        assert($query instanceof GetReceipt);

        $receipt = $this->receipts->findById($query->id);
        if ($receipt === null) {
            throw new NotFoundException("Beleg \"{$query->id}\" wurde nicht gefunden.");
        }

        $supplier = $this->suppliers->findById($receipt->supplierId);

        $inventoryNumbers = array_map(
            fn ($inventoryNumber) => new ReceiptInventoryNumberEntry(
                $inventoryNumber,
                $this->articles->findById($inventoryNumber->articleId),
            ),
            $this->inventoryNumbers->findByReceipt($receipt->id),
        );

        return new ReceiptDetail($receipt, $supplier, $inventoryNumbers);
    }
}
