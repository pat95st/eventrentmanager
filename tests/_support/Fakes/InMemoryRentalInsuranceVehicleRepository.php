<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Insurance\RentalInsuranceVehicleRepositoryInterface;

final class InMemoryRentalInsuranceVehicleRepository implements RentalInsuranceVehicleRepositoryInterface
{
    /** @var array<string, list<string>> rental id => vehicle ids */
    public array $vehicleIds = [];

    public function vehicleIdsForRental(string $rentalId): array
    {
        return $this->vehicleIds[$rentalId] ?? [];
    }

    public function add(string $rentalId, string $vehicleId): void
    {
        if (!in_array($vehicleId, $this->vehicleIds[$rentalId] ?? [], true)) {
            $this->vehicleIds[$rentalId][] = $vehicleId;
        }
    }

    public function remove(string $rentalId, string $vehicleId): void
    {
        $this->vehicleIds[$rentalId] = array_values(array_filter(
            $this->vehicleIds[$rentalId] ?? [],
            static fn (string $id) => $id !== $vehicleId,
        ));
    }
}
