<?php

declare(strict_types=1);

namespace App\Application\Manufacturer\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

final class ListManufacturersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ManufacturerRepositoryInterface $manufacturers)
    {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListManufacturers);

        return $this->manufacturers->findAll($query->search, $query->limit);
    }
}
