<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandInterface;

/** Settings page "Fahrzeuge": default cost per km for cars and trailers (migration 181). */
final class SaveVehicleSettings implements CommandInterface
{
    public function __construct(
        public readonly string $defaultCostPerKmAuto,
        public readonly string $defaultCostPerKmTrailer,
    ) {
    }
}
