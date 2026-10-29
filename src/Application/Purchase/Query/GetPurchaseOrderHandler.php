<?php

declare(strict_types=1);

namespace App\Application\Purchase\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;

final class GetPurchaseOrderHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(QueryInterface $query): PurchaseOrderDetail
    {
        assert($query instanceof GetPurchaseOrder);

        $order = $this->purchaseOrders->findById($query->id);
        if ($order === null) {
            throw new NotFoundException("Bestellung \"{$query->id}\" wurde nicht gefunden.");
        }

        // Siehe GetInvoiceHandler für die identische Begründung des
        // kleinen Hersteller-Caches.
        $manufacturerCache = [];
        $manufacturerFor = function (string $manufacturerId) use (&$manufacturerCache) {
            if (!array_key_exists($manufacturerId, $manufacturerCache)) {
                $manufacturerCache[$manufacturerId] = $this->manufacturers->findById($manufacturerId);
            }

            return $manufacturerCache[$manufacturerId];
        };

        $rawItems = $this->purchaseOrders->findItemsByOrder($order->id);
        $items = array_values(array_filter(array_map(
            function ($item) use ($manufacturerFor) {
                $article = $this->articles->findById($item->articleId);

                return $article !== null ? new PurchaseOrderItemWithArticle($item, $article, $manufacturerFor(($article->manufacturerId ?? ''))) : null;
            },
            $rawItems
        )));

        return new PurchaseOrderDetail($order, $this->suppliers->findById($order->supplierId), $items);
    }
}
