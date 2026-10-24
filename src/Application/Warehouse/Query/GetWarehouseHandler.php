<?php

declare(strict_types=1);

namespace App\Application\Warehouse\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Warehouse\Warehouse;
use App\Domain\Warehouse\WarehouseRepositoryInterface;

final class GetWarehouseHandler implements QueryHandlerInterface
{
    public function __construct(private readonly WarehouseRepositoryInterface $warehouses)
    {
    }

    public function handle(QueryInterface $query): Warehouse
    {
        assert($query instanceof GetWarehouse);

        $warehouse = $this->warehouses->findById($query->id);
        if ($warehouse === null) {
            throw new NotFoundException("Lagerort \"{$query->id}\" wurde nicht gefunden.");
        }

        return $warehouse;
    }
}
