<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandInterface;

/** Removes a vehicle from the insurance list of an event. */
final class RemoveRentalInsuranceVehicle implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $vehicleId,
    ) {
    }
}
