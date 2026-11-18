<?php

declare(strict_types=1);

namespace App\Application\Routing\Command;

use App\Application\Command\CommandInterface;

/** Plans a vehicle for an event; a trailer may name the car that pulls it. */
final class AddPlannedVehicle implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $vehicleId,
        public readonly string $towingVehicleId = '',
    ) {
    }
}
