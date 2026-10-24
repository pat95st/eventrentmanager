<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Vehicle\RentalVehicleTow;
use App\Domain\Vehicle\RentalVehicleTowRepositoryInterface;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Ordnet einem Anhänger (towedVehicleId) für diese Veranstaltung ein
 * Zugfahrzeug (towingVehicleId) zu, für die Anhängelast-Prüfung (siehe
 * VehicleLoadCalculator::isTowOverloaded). Das Zugfahrzeug muss ein Auto
 * mit vorhandener Anhängekupplung sein, das gezogene Fahrzeug ein Anhänger
 * — sonst ValidationException. Eine bestehende Zuordnung des Anhängers
 * wird durch die neue ersetzt (ein Anhänger hat je Veranstaltung höchstens
 * ein Zugfahrzeug).
 */
final class SetRentalVehicleTowHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly RentalVehicleTowRepositoryInterface $vehicleTows,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SetRentalVehicleTow);

        $rental = $this->rentals->findById($command->rentalId);
        if ($rental === null) {
            throw new NotFoundException("Vermietung \"{$command->rentalId}\" wurde nicht gefunden.");
        }

        $towedVehicle = $this->vehicles->findById($command->towedVehicleId);
        $towingVehicle = $this->vehicles->findById($command->towingVehicleId);

        $errors = [];
        if ($towedVehicle === null) {
            $errors['towedVehicleId'] = 'Der ausgewählte Anhänger wurde nicht gefunden.';
        } elseif ($towedVehicle->category !== VehicleCategory::Anhaenger) {
            $errors['towedVehicleId'] = 'Das gezogene Fahrzeug muss ein Anhänger sein.';
        }

        if ($towingVehicle === null) {
            $errors['towingVehicleId'] = 'Das ausgewählte Zugfahrzeug wurde nicht gefunden.';
        } elseif ($towingVehicle->category !== VehicleCategory::Auto) {
            $errors['towingVehicleId'] = 'Das Zugfahrzeug muss ein Auto sein.';
        } elseif ($towingVehicle->towHitchAvailable !== true) {
            $errors['towingVehicleId'] = 'Das ausgewählte Auto hat laut Fahrzeugdaten keine Anhängekupplung.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $existing = $this->vehicleTows->findByRentalAndTowedVehicle($command->rentalId, $command->towedVehicleId);

        $tow = new RentalVehicleTow(
            id: $existing?->id ?? Uuid::v4(),
            rentalId: $command->rentalId,
            towedVehicleId: $command->towedVehicleId,
            towingVehicleId: $command->towingVehicleId,
            createdAt: $existing?->createdAt ?? new DateTimeImmutable(),
        );

        $this->vehicleTows->save($tow);

        return null;
    }
}
