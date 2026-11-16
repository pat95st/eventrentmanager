<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandInterface;

/** Puts a vehicle on the insurance list of an event. */
final class AddRentalInsuranceVehicle implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $vehicleId,
    ) {
    }
}
