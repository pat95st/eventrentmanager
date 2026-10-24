<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use DateTimeImmutable;

/**
 * Ein Eintrag der Beladungsliste: ordnet eine konkret ausgegebene
 * Inventarnummer (App\Domain\Rental\RentalIssuedItem) einem Fahrzeug zu.
 * Ein Posten kann immer nur in genau einem Fahrzeug verladen sein (siehe
 * UNIQUE-Constraint auf rental_issued_item_id in der Datenbank).
 */
final class RentalVehicleLoad
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $rentalIssuedItemId,
        public string $vehicleId,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
