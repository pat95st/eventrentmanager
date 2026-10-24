<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;

final class ListVehiclesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VehicleRepositoryInterface $vehicles)
    {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListVehicles);

        $offset = $query->pageSize !== null ? PaginatedResult::offsetFor($query->page ?? 1, $query->pageSize) : null;

        $items = $this->vehicles->findAll($query->search, $query->pageSize, $offset);
        $totalCount = $this->vehicles->countAll($query->search);

        return new PaginatedResult($items, $totalCount, $query->page ?? 1, $query->pageSize ?? max($totalCount, 1));
    }
}
