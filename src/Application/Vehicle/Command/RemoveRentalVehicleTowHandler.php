<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Vehicle\RentalVehicleTowRepositoryInterface;

final class RemoveRentalVehicleTowHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RentalVehicleTowRepositoryInterface $vehicleTows)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RemoveRentalVehicleTow);

        $this->vehicleTows->deleteByRentalAndTowedVehicle($command->rentalId, $command->towedVehicleId);

        return null;
    }
}
