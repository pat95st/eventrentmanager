<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use DateTimeImmutable;

/**
 * Zugfahrzeug-Zuordnung je Veranstaltung: welches Auto (towingVehicleId)
 * zieht welchen Anhänger (towedVehicleId), für die Prüfung "Leergewicht
 * Anhänger + Beladung Anhänger <= Anhängelast Auto" (siehe
 * VehicleLoadCalculator). Ein Anhänger hat je Veranstaltung höchstens ein
 * Zugfahrzeug (siehe UNIQUE-Constraint auf (rental_id, towed_vehicle_id)
 * in der Datenbank).
 */
final class RentalVehicleTow
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $towedVehicleId,
        public string $towingVehicleId,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
