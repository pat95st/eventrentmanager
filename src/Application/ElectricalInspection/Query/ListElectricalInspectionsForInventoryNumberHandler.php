<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\ElectricalInspection\ElectricalInspection;
use App\Domain\ElectricalInspection\ElectricalInspectionRepositoryInterface;

final class ListElectricalInspectionsForInventoryNumberHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ElectricalInspectionRepositoryInterface $inspections)
    {
    }

    /** @return ElectricalInspection[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListElectricalInspectionsForInventoryNumber);

        return $this->inspections->findByInventoryNumber($query->inventoryNumberId);
    }
}
