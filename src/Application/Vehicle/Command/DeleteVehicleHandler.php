<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Vehicle\VehicleRepositoryInterface;

final class DeleteVehicleHandler implements CommandHandlerInterface
{
    public function __construct(private readonly VehicleRepositoryInterface $vehicles)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteVehicle);

        $vehicle = $this->vehicles->findById($command->id);
        if ($vehicle === null) {
            throw new NotFoundException("Fahrzeug \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->vehicles->isUsedByVehicleLoads($command->id)) {
            throw new ConflictException('Dieses Fahrzeug ist noch in einer Beladungsliste zugeordnet und kann nicht gelöscht werden.');
        }

        if ($this->vehicles->isUsedByVehicleTows($command->id)) {
            throw new ConflictException('Dieses Fahrzeug ist noch einer Zugfahrzeug-/Anhänger-Zuordnung zugeordnet und kann nicht gelöscht werden.');
        }

        $this->vehicles->delete($command->id);

        return null;
    }
}
