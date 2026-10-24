<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

interface RentalVehicleLoadRepositoryInterface
{
    public function save(RentalVehicleLoad $load): void;

    public function findById(string $id): ?RentalVehicleLoad;

    /** @return RentalVehicleLoad[] */
    public function findByRental(string $rentalId): array;

    public function findByRentalIssuedItem(string $rentalIssuedItemId): ?RentalVehicleLoad;

    /** @return RentalVehicleLoad[] */
    public function findByVehicle(string $vehicleId): array;

    public function delete(string $id): void;

    public function deleteByRentalIssuedItem(string $rentalIssuedItemId): void;
}
