<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Insurance\RentalInsuranceVehicleRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;

/**
 * Adds/removes vehicles on the insurance list of an event (migration 170).
 * Returns the affected vehicle's name for the flash message.
 */
final class RentalInsuranceVehicleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly RentalInsuranceVehicleRepositoryInterface $insuranceVehicles,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddRentalInsuranceVehicle || $command instanceof RemoveRentalInsuranceVehicle);

        if ($this->rentals->findById($command->rentalId) === null) {
            throw new NotFoundException('Veranstaltung wurde nicht gefunden.');
        }
        $vehicle = $this->vehicles->findById($command->vehicleId);
        if ($vehicle === null) {
            throw new NotFoundException('Fahrzeug wurde nicht gefunden.');
        }

        if ($command instanceof AddRentalInsuranceVehicle) {
            $this->insuranceVehicles->add($command->rentalId, $vehicle->id);
        } else {
            $this->insuranceVehicles->remove($command->rentalId, $vehicle->id);
        }

        return $vehicle->name;
    }
}
