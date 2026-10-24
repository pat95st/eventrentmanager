<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Vehicle\RentalVehicleLoad;
use App\Domain\Vehicle\RentalVehicleLoadRepositoryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Ordnet einen bereits ausgegebenen Posten (konkrete Inventarnummer) der
 * Beladungsliste einem Fahrzeug zu. Ein Posten kann immer nur in einem
 * Fahrzeug verladen sein — eine bestehende Zuordnung wird durch die neue
 * ersetzt (siehe RentalVehicleLoadRepositoryInterface::findByRentalIssuedItem).
 */
final class AssignRentalVehicleLoadHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly RentalVehicleLoadRepositoryInterface $vehicleLoads,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof AssignRentalVehicleLoad);

        $issuedItem = $this->issuedItems->findById($command->rentalIssuedItemId);
        if ($issuedItem === null) {
            throw new NotFoundException("Ausgegebener Posten \"{$command->rentalIssuedItemId}\" wurde nicht gefunden.");
        }

        $vehicle = $this->vehicles->findById($command->vehicleId);
        if ($vehicle === null) {
            throw new NotFoundException("Fahrzeug \"{$command->vehicleId}\" wurde nicht gefunden.");
        }

        $existing = $this->vehicleLoads->findByRentalIssuedItem($command->rentalIssuedItemId);

        $load = new RentalVehicleLoad(
            id: $existing?->id ?? Uuid::v4(),
            rentalId: $issuedItem->rentalId,
            rentalIssuedItemId: $issuedItem->id,
            vehicleId: $vehicle->id,
            createdAt: $existing?->createdAt ?? new DateTimeImmutable(),
        );

        $this->vehicleLoads->save($load);

        return null;
    }
}
