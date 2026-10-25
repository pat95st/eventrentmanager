<?php

declare(strict_types=1);

namespace App\Application\Sale\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Sale\SaleRepositoryInterface;

final class ListSalesHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly SaleRepositoryInterface $sales,
        private readonly ArticleRepositoryInterface $articles,
        private readonly CustomerRepositoryInterface $customers,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListSales);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $sales = $this->sales->search($query->searchTerm, $query->pageSize, $offset);
        $totalCount = $this->sales->countSearch($query->searchTerm);

        // Siehe GetInvoiceHandler für die identische Begründung des
        // kleinen Hersteller-Caches.
        $manufacturerCache = [];

        $entries = array_map(
            function ($sale) use (&$manufacturerCache) {
                $article = $this->articles->findById($sale->articleId);
                $manufacturer = null;
                if ($article !== null) {
                    if (!array_key_exists($article->manufacturerId, $manufacturerCache)) {
                        $manufacturerCache[$article->manufacturerId] = $this->manufacturers->findById($article->manufacturerId);
                    }
                    $manufacturer = $manufacturerCache[$article->manufacturerId];
                }

                return new SaleListEntry(
                    $sale,
                    $article,
                    $sale->customerId !== null ? $this->customers->findById($sale->customerId) : null,
                    $manufacturer,
                );
            },
            $sales
        );

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
