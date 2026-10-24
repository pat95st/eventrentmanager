<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateVehicleHandler implements CommandHandlerInterface
{
    use ValidatesVehicleInput;

    public function __construct(private readonly VehicleRepositoryInterface $vehicles)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateVehicle);

        [$errors, $data] = $this->validateVehicleInput(
            $command->category,
            $command->name,
            $command->licensePlate,
            $command->payloadKg,
            $command->loadAreaDimensions,
            $command->towHitchAvailable,
            $command->towCapacityKg,
            $command->emptyWeightKg,
            $command->notes,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $vehicle = new Vehicle(
            id: Uuid::v4(),
            category: $data['category'],
            name: $data['name'],
            licensePlate: $data['licensePlate'],
            payloadKg: $data['payloadKg'],
            loadAreaDimensions: $data['loadAreaDimensions'],
            towHitchAvailable: $data['towHitchAvailable'],
            towCapacityKg: $data['towCapacityKg'],
            emptyWeightKg: $data['emptyWeightKg'],
            notes: $data['notes'],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->vehicles->save($vehicle);

        return $vehicle->id;
    }
}
