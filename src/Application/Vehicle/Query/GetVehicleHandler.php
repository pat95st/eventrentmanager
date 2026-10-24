<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleRepositoryInterface;

final class GetVehicleHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VehicleRepositoryInterface $vehicles)
    {
    }

    public function handle(QueryInterface $query): Vehicle
    {
        assert($query instanceof GetVehicle);

        $vehicle = $this->vehicles->findById($query->id);
        if ($vehicle === null) {
            throw new NotFoundException("Fahrzeug \"{$query->id}\" wurde nicht gefunden.");
        }

        return $vehicle;
    }
}
