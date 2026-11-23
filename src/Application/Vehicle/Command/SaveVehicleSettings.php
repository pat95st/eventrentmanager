<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandInterface;

/** Settings page "Fahrzeuge": default cost per km for cars and trailers (migration 181)
 * and the markup on travel costs of tours (migration 182). */
final class SaveVehicleSettings implements CommandInterface
{
    public function __construct(
        public readonly string $defaultCostPerKmAuto,
        public readonly string $defaultCostPerKmTrailer,
        // Migration 182: markup on travel costs in percent; null = keep the stored value.
        public readonly ?string $travelCostMarkupPercent = null,
        // Migration 186: null keeps the current value.
        public readonly ?bool $eventTravelCostsDefault = null,
    ) {
    }
}
