<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/** Vehicle planned for an event before issuing (migration 178). */
final class PlannedVehicle
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $vehicleId,
        public ?string $towingVehicleId = null, // car that pulls this trailer
    ) {
    }
}
