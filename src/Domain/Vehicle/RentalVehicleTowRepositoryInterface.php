<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

interface RentalVehicleTowRepositoryInterface
{
    public function save(RentalVehicleTow $tow): void;

    public function findById(string $id): ?RentalVehicleTow;

    /** @return RentalVehicleTow[] */
    public function findByRental(string $rentalId): array;

    public function findByRentalAndTowedVehicle(string $rentalId, string $towedVehicleId): ?RentalVehicleTow;

    /** Alle Zuordnungen, in denen dieses Fahrzeug vorkommt (als Zugfahrzeug ODER als Anhänger). */
    public function findByVehicle(string $vehicleId): array;

    public function delete(string $id): void;

    public function deleteByRentalAndTowedVehicle(string $rentalId, string $towedVehicleId): void;
}
