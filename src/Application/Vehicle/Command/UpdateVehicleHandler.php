<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use DateTimeImmutable;

final class UpdateVehicleHandler implements CommandHandlerInterface
{
    use ValidatesVehicleInput;

    public function __construct(
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly ?UserRepositoryInterface $users = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateVehicle);

        $vehicle = $this->vehicles->findById($command->id);
        if ($vehicle === null) {
            throw new NotFoundException("Fahrzeug \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateVehicleInput(
            $command->category,
            $command->name,
            $command->licensePlate,
            $command->payloadKg,
            $command->loadAreaLengthCm,
            $command->loadAreaWidthCm,
            $command->loadAreaHeightCm,
            $command->loadAreaOpenTop,
            $command->towHitchAvailable,
            $command->towCapacityKg,
            $command->emptyWeightKg,
            $command->notes,
            $command->acquisitionYear,
            $command->purchasePrice,
            $command->currentValue,
            $command->heightCm,
            $command->widthCm,
            $command->lengthCm,
            $command->grossWeightKg,
            $command->passengerSeats,
            $command->costPerKm,
        );
        $ownerUserId = $this->validatePrivateOwner($command->isPrivate, $command->ownerUserId, $vehicle->ownerUserId, $this->users, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $vehicle->category = $data['category'];
        $vehicle->name = $data['name'];
        $vehicle->licensePlate = $data['licensePlate'];
        $vehicle->payloadKg = $data['payloadKg'];
        $vehicle->loadAreaLengthCm = $data['loadAreaLengthCm'];
        $vehicle->loadAreaWidthCm = $data['loadAreaWidthCm'];
        $vehicle->loadAreaHeightCm = $data['loadAreaHeightCm'];
        $vehicle->loadAreaOpenTop = $data['loadAreaOpenTop'];
        $vehicle->towHitchAvailable = $data['towHitchAvailable'];
        $vehicle->towCapacityKg = $data['towCapacityKg'];
        $vehicle->emptyWeightKg = $data['emptyWeightKg'];
        $vehicle->notes = $data['notes'];
        $vehicle->acquisitionYear = $data['acquisitionYear'];
        $vehicle->purchasePrice = $data['purchasePrice'];
        $vehicle->currentValue = $data['currentValue'];
        $vehicle->heightCm = $data['heightCm'];
        $vehicle->widthCm = $data['widthCm'];
        $vehicle->lengthCm = $data['lengthCm'];
        $vehicle->grossWeightKg = $data['grossWeightKg'];
        $vehicle->passengerSeats = $data['passengerSeats'];
        $vehicle->costPerKm = $data['costPerKm'];
        $vehicle->isPrivate = $command->isPrivate;
        $vehicle->ownerUserId = $ownerUserId;
        $vehicle->updatedAt = new DateTimeImmutable();

        $this->vehicles->save($vehicle);

        return null;
    }
}
