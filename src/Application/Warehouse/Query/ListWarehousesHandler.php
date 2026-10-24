<?php

declare(strict_types=1);

namespace App\Application\Warehouse\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Warehouse\WarehouseRepositoryInterface;

final class ListWarehousesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly WarehouseRepositoryInterface $warehouses)
    {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListWarehouses);

        $totalCount = $this->warehouses->countAll($query->search);

        if ($query->pageSize === null) {
            // Kein Limit angefordert (Dropdown-Nutzung): alle Treffer laden.
            $items = $this->warehouses->findAll($query->search, max($totalCount, 1), 0);

            return new PaginatedResult($items, $totalCount, 1, max($totalCount, 1));
        }

        $offset = PaginatedResult::offsetFor($query->page ?? 1, $query->pageSize);
        $items = $this->warehouses->findAll($query->search, $query->pageSize, $offset);

        return new PaginatedResult($items, $totalCount, $query->page ?? 1, $query->pageSize);
    }
}
