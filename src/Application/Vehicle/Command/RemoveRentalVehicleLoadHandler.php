<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Vehicle\RentalVehicleLoadRepositoryInterface;

final class RemoveRentalVehicleLoadHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly RentalVehicleLoadRepositoryInterface $vehicleLoads,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RemoveRentalVehicleLoad);

        $issuedItem = $this->issuedItems->findById($command->rentalIssuedItemId);
        if ($issuedItem === null) {
            throw new NotFoundException("Ausgegebener Posten \"{$command->rentalIssuedItemId}\" wurde nicht gefunden.");
        }

        $this->vehicleLoads->deleteByRentalIssuedItem($command->rentalIssuedItemId);

        return null;
    }
}
