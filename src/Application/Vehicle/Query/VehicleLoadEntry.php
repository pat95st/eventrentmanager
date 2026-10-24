<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Query;

use App\Application\Rental\Query\RentalIssuedItemWithDetails;

/**
 * Ein in einem Fahrzeug verladener Posten der Beladungsliste: der
 * tatsächlich ausgegebene Posten (konkrete Inventarnummer) zusammen mit
 * seinem aus Article::$weightValue/$weightUnit umgerechneten Gewicht in kg
 * (siehe App\Domain\Article\WeightConverter). null = Gewicht am Artikel
 * nicht hinterlegt bzw. nicht verwertbar.
 */
final class VehicleLoadEntry
{
    public function __construct(
        public readonly RentalIssuedItemWithDetails $issuedItem,
        public readonly ?float $weightKg,
    ) {
    }
}
