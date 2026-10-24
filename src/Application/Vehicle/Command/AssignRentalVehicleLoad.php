<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandInterface;

final class AssignRentalVehicleLoad implements CommandInterface
{
    public function __construct(
        public readonly string $rentalIssuedItemId,
        public readonly string $vehicleId,
    ) {
    }
}
