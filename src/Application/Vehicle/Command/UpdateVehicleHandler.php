<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use DateTimeImmutable;

final class UpdateVehicleHandler implements CommandHandlerInterface
{
    use ValidatesVehicleInput;

    public function __construct(private readonly VehicleRepositoryInterface $vehicles)
    {
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
        );

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
        $vehicle->updatedAt = new DateTimeImmutable();

        $this->vehicles->save($vehicle);

        return null;
    }
}
