<?php

declare(strict_types=1);

namespace App\Domain\Insurance;

/**
 * Vehicles explicitly put on the insurance list of an event (migration 170).
 * The values themselves (acquisition year, purchase price, current value)
 * are maintained at the vehicle.
 */
interface RentalInsuranceVehicleRepositoryInterface
{
    /** @return list<string> vehicle ids on the insurance list of the event */
    public function vehicleIdsForRental(string $rentalId): array;

    /** Idempotent: adding a vehicle twice has no effect. */
    public function add(string $rentalId, string $vehicleId): void;

    public function remove(string $rentalId, string $vehicleId): void;
}
