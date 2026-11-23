<?php

declare(strict_types=1);

namespace App\Application\TravelCost;

use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleSettings;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;

/**
 * Price per km of an event's travel costs (migrations 181/182): sum of the
 * cost per km of all planned vehicles (each vehicle once) plus the markup
 * from the vehicle settings, rounded to whole cents. Shared by tours and
 * events with planned trips (migration 186).
 */
final class TravelCostRate
{
    public function __construct(
        private readonly PlannedVehicleRepositoryInterface $plannedVehicles,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly VehicleSettingsRepositoryInterface $vehicleSettings,
    ) {
    }

    /**
     * @return array{vehicles: list<array{name: string, costPerKm: string, isDefault: bool}>, costPerKm: string, ratePerKm: string, settings: VehicleSettings}
     */
    public function forRental(string $rentalId): array
    {
        $settings = $this->vehicleSettings->get();
        $vehicles = [];
        $cost = 0.0;
        $seen = [];
        foreach ($this->plannedVehicles->findByRental($rentalId) as $planned) {
            if (isset($seen[$planned->vehicleId])) {
                continue;
            }
            $seen[$planned->vehicleId] = true;
            $vehicle = $this->vehicles->findById($planned->vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $vehicleCost = $vehicle->effectiveCostPerKm($settings);
            $cost += (float) $vehicleCost;
            $vehicles[] = ['name' => $vehicle->name, 'costPerKm' => $vehicleCost, 'isDefault' => $vehicle->usesDefaultCostPerKm()];
        }

        return [
            'vehicles' => $vehicles,
            'costPerKm' => number_format($cost, 2, '.', ''),
            'ratePerKm' => $settings->billedRatePerKm($cost),
            'settings' => $settings,
        ];
    }
}
