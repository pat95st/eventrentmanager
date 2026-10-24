<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\ElectricalInspection\ElectricalInspection;
use App\Domain\ElectricalInspection\ElectricalInspectionRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class GetElectricalInspectionHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ElectricalInspectionRepositoryInterface $inspections)
    {
    }

    public function handle(QueryInterface $query): ElectricalInspection
    {
        assert($query instanceof GetElectricalInspection);

        $inspection = $this->inspections->findById($query->id);
        if ($inspection === null) {
            throw new NotFoundException("Elektroprüfung \"{$query->id}\" wurde nicht gefunden.");
        }

        return $inspection;
    }
}
