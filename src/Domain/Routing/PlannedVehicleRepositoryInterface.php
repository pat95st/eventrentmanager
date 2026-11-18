<?php

declare(strict_types=1);

namespace App\Domain\Routing;

interface PlannedVehicleRepositoryInterface
{
    /** @return PlannedVehicle[] */
    public function findByRental(string $rentalId): array;

    public function save(PlannedVehicle $vehicle): void;

    public function delete(string $id): void;
}
