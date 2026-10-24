<?php

declare(strict_types=1);

namespace App\Application\Sale\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Sale\SaleRepositoryInterface;

final class ListSalesHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly SaleRepositoryInterface $sales,
        private readonly ArticleRepositoryInterface $articles,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListSales);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $sales = $this->sales->search($query->searchTerm, $query->pageSize, $offset);
        $totalCount = $this->sales->countSearch($query->searchTerm);

        $entries = array_map(
            fn ($sale) => new SaleListEntry(
                $sale,
                $this->articles->findById($sale->articleId),
                $sale->customerId !== null ? $this->customers->findById($sale->customerId) : null,
            ),
            $sales
        );

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
