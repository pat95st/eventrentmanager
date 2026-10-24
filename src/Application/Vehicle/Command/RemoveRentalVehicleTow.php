<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandInterface;

final class RemoveRentalVehicleTow implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $towedVehicleId,
    ) {
    }
}
