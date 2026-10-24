<?php

declare(strict_types=1);

namespace App\Application\Supplier\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;

final class ListSuppliersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly SupplierRepositoryInterface $suppliers)
    {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListSuppliers);

        $offset = $query->pageSize !== null ? PaginatedResult::offsetFor($query->page ?? 1, $query->pageSize) : null;

        $items = $this->suppliers->findAll($query->search, $query->pageSize, $offset);
        $totalCount = $this->suppliers->countAll($query->search);

        return new PaginatedResult($items, $totalCount, $query->page ?? 1, $query->pageSize ?? max($totalCount, 1));
    }
}
