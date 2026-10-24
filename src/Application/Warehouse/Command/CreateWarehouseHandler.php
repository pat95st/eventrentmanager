<?php

declare(strict_types=1);

namespace App\Application\Warehouse\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Warehouse\Warehouse;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use App\Infrastructure\Geocoding\GeocoderInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateWarehouseHandler implements CommandHandlerInterface
{
    use ValidatesWarehouseInput;

    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly GeocoderInterface $geocoder,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateWarehouse);

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

        // Geokodierung ist "best effort" — schlägt sie fehl, wird der
        // Lagerort trotzdem ohne Koordinaten gespeichert (siehe
        // CreateVenueHandler für dasselbe Muster).
        $coordinates = $this->geocoder->geocode($data['street'], $data['postalCode'], $data['city'], $data['country']);

        $now = new DateTimeImmutable();
        $warehouse = new Warehouse(
            id: Uuid::v4(),
            name: $data['name'],
            street: $data['street'],
            postalCode: $data['postalCode'],
            city: $data['city'],
            country: $data['country'],
            notes: $data['notes'],
            latitude: $coordinates[0] ?? null,
            longitude: $coordinates[1] ?? null,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->warehouses->save($warehouse);

        return $warehouse->id;
    }
}
