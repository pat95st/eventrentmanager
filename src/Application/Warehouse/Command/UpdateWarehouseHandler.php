<?php

declare(strict_types=1);

namespace App\Application\Warehouse\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use App\Infrastructure\Geocoding\GeocoderInterface;
use DateTimeImmutable;

final class UpdateWarehouseHandler implements CommandHandlerInterface
{
    use ValidatesWarehouseInput;

    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly GeocoderInterface $geocoder,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateWarehouse);

        $warehouse = $this->warehouses->findById($command->id);
        if ($warehouse === null) {
            throw new NotFoundException("Lagerort \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateWarehouseInput(
            $command->name,
            $command->street,
            $command->postalCode,
            $command->city,
            $command->country,
            $command->notes,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $addressChanged = $warehouse->street !== $data['street']
            || $warehouse->postalCode !== $data['postalCode']
            || $warehouse->city !== $data['city']
            || $warehouse->country !== $data['country'];

        if ($addressChanged) {
            $coordinates = $this->geocoder->geocode($data['street'], $data['postalCode'], $data['city'], $data['country']);
            $warehouse->latitude = $coordinates[0] ?? null;
            $warehouse->longitude = $coordinates[1] ?? null;
        }

        $warehouse->name = $data['name'];
        $warehouse->street = $data['street'];
        $warehouse->postalCode = $data['postalCode'];
        $warehouse->city = $data['city'];
        $warehouse->country = $data['country'];
        $warehouse->notes = $data['notes'];
        $warehouse->updatedAt = new DateTimeImmutable();

        $this->warehouses->save($warehouse);

        return null;
    }
}
