<?php

declare(strict_types=1);

namespace App\Application\Supplier\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Supplier\Supplier;
use App\Domain\Supplier\SupplierRepositoryInterface;

final class GetSupplierHandler implements QueryHandlerInterface
{
    public function __construct(private readonly SupplierRepositoryInterface $suppliers)
    {
    }

    public function handle(QueryInterface $query): Supplier
    {
        assert($query instanceof GetSupplier);

        $supplier = $this->suppliers->findById($query->id);
        if ($supplier === null) {
            throw new NotFoundException("Lieferant \"{$query->id}\" wurde nicht gefunden.");
        }

        return $supplier;
    }
}
