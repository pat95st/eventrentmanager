<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Query;

use App\Domain\Rental\Rental;
use App\Domain\Vehicle\Vehicle;

/**
 * Gesamtes View-Modell der Beladungsliste einer Veranstaltung (siehe
 * GetRentalLoadingListHandler): für jedes Fahrzeug im Fuhrpark dessen
 * aktuelle Beladung/Überladungsstatus, plus die noch keinem Fahrzeug
 * zugeordneten, bereits ausgegebenen Posten dieser Veranstaltung.
 */
final class RentalLoadingList
{
    /**
     * @param VehicleLoadSummary[] $vehicleSummaries
     * @param VehicleLoadEntry[] $unassignedItems
     * @param Vehicle[] $allVehicles
     */
    public function __construct(
        public readonly Rental $rental,
        public readonly array $vehicleSummaries,
        public readonly array $unassignedItems,
        public readonly array $allVehicles,
    ) {
    }
}
