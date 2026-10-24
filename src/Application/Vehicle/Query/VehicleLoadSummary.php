<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Query;

use App\Domain\Vehicle\Vehicle;

/**
 * Beladungs-/Überladungsstatus eines einzelnen Fahrzeugs innerhalb der
 * Beladungsliste einer Veranstaltung (siehe RentalLoadingList,
 * VehicleLoadCalculator). towingVehicle/towOverloaded/trailerTotalWeightKg
 * sind nur gesetzt, wenn dieses Fahrzeug ein Anhänger ist, der in dieser
 * Veranstaltung einem Zugfahrzeug zugeordnet ist.
 */
final class VehicleLoadSummary
{
    /** @param VehicleLoadEntry[] $entries */
    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly array $entries,
        public readonly ?float $totalWeightKg,
        public readonly ?float $payloadKg,
        public readonly ?bool $overloaded,
        public readonly ?float $remainingPayloadKg,
        public readonly ?Vehicle $towingVehicle = null,
        public readonly ?bool $towOverloaded = null,
        public readonly ?float $trailerTotalWeightKg = null,
    ) {
    }
}
