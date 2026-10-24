<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\Rental\Query\RentalIssuedItemWithDetails;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\WeightConverter;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Vehicle\RentalVehicleLoadRepositoryInterface;
use App\Domain\Vehicle\RentalVehicleTowRepositoryInterface;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleLoadCalculator;
use App\Domain\Vehicle\VehicleRepositoryInterface;

final class GetRentalLoadingListHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly ArticleRepositoryInterface $articles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly RentalVehicleLoadRepositoryInterface $vehicleLoads,
        private readonly RentalVehicleTowRepositoryInterface $vehicleTows,
    ) {
    }

    public function handle(QueryInterface $query): RentalLoadingList
    {
        assert($query instanceof GetRentalLoadingList);

        $rental = $this->rentals->findById($query->rentalId);
        if ($rental === null) {
            throw new NotFoundException("Vermietung \"{$query->rentalId}\" wurde nicht gefunden.");
        }

        // Nur tatsächlich ausgegebene Posten (konkrete Inventarnummer) können
        // in der Beladungsliste einem Fahrzeug zugeordnet werden (siehe
        // Projekt-Entscheidung "pro einzelner Inventarnummer") — rein
        // geplante Posten (RentalItem, Artikel+Menge) erscheinen hier nicht.
        $rawIssuedItems = $this->issuedItems->findByRental($rental->id);
        $issuedItemsWithDetails = [];
        foreach ($rawIssuedItems as $issuedItem) {
            $article = $this->articles->findById($issuedItem->articleId);
            $inventoryNumber = $this->inventoryNumbers->findById($issuedItem->inventoryNumberId);
            if ($article === null || $inventoryNumber === null) {
                continue;
            }
            $issuedItemsWithDetails[$issuedItem->id] = new RentalIssuedItemWithDetails($issuedItem, $inventoryNumber, $article);
        }

        $loads = $this->vehicleLoads->findByRental($rental->id);
        $vehicleIdByIssuedItemId = [];
        foreach ($loads as $load) {
            $vehicleIdByIssuedItemId[$load->rentalIssuedItemId] = $load->vehicleId;
        }

        $tows = $this->vehicleTows->findByRental($rental->id);
        $towingVehicleIdByTowedVehicleId = [];
        foreach ($tows as $tow) {
            $towingVehicleIdByTowedVehicleId[$tow->towedVehicleId] = $tow->towingVehicleId;
        }

        $allVehicles = $this->vehicles->findAll();
        $vehiclesById = [];
        foreach ($allVehicles as $vehicle) {
            $vehiclesById[$vehicle->id] = $vehicle;
        }

        // Je Fahrzeug die zugeordneten Posten einsammeln; alles ohne
        // Zuordnung landet in $unassignedItems.
        $entriesByVehicleId = [];
        $unassignedItems = [];
        foreach ($issuedItemsWithDetails as $issuedItemId => $details) {
            $weightKg = WeightConverter::toKg($details->article->weightValue, $details->article->weightUnit);
            $entry = new VehicleLoadEntry($details, $weightKg);

            $vehicleId = $vehicleIdByIssuedItemId[$issuedItemId] ?? null;
            if ($vehicleId === null || !isset($vehiclesById[$vehicleId])) {
                $unassignedItems[] = $entry;

                continue;
            }

            $entriesByVehicleId[$vehicleId][] = $entry;
        }

        $vehicleSummaries = [];
        foreach ($allVehicles as $vehicle) {
            $entries = $entriesByVehicleId[$vehicle->id] ?? [];
            $weights = array_map(static fn (VehicleLoadEntry $entry) => $entry->weightKg, $entries);
            $totalWeightKg = VehicleLoadCalculator::totalLoadedWeightKg($weights);
            $payloadKg = $vehicle->payloadKg !== null ? (float) $vehicle->payloadKg : null;

            $towingVehicle = null;
            $towOverloaded = null;
            $trailerTotalWeightKg = null;
            if ($vehicle->category === VehicleCategory::Anhaenger) {
                $towingVehicleId = $towingVehicleIdByTowedVehicleId[$vehicle->id] ?? null;
                $towingVehicle = $towingVehicleId !== null ? ($vehiclesById[$towingVehicleId] ?? null) : null;

                if ($towingVehicle !== null) {
                    $emptyWeightKg = $vehicle->emptyWeightKg !== null ? (float) $vehicle->emptyWeightKg : null;
                    $towCapacityKg = $towingVehicle->towCapacityKg !== null ? (float) $towingVehicle->towCapacityKg : null;
                    $trailerTotalWeightKg = VehicleLoadCalculator::trailerTotalWeightKg($emptyWeightKg, $totalWeightKg);
                    $towOverloaded = VehicleLoadCalculator::isTowOverloaded($towCapacityKg, $emptyWeightKg, $totalWeightKg);
                }
            }

            $vehicleSummaries[] = new VehicleLoadSummary(
                vehicle: $vehicle,
                entries: $entries,
                totalWeightKg: $totalWeightKg,
                payloadKg: $payloadKg,
                overloaded: VehicleLoadCalculator::isOverloaded($payloadKg, $totalWeightKg),
                remainingPayloadKg: VehicleLoadCalculator::remainingPayloadKg($payloadKg, $totalWeightKg),
                towingVehicle: $towingVehicle,
                towOverloaded: $towOverloaded,
                trailerTotalWeightKg: $trailerTotalWeightKg,
            );
        }

        return new RentalLoadingList($rental, $vehicleSummaries, $unassignedItems, $allVehicles);
    }
}
