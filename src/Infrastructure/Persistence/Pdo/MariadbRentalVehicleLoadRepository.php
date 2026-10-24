<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Vehicle\RentalVehicleLoad;
use App\Domain\Vehicle\RentalVehicleLoadRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalVehicleLoadRepository implements RentalVehicleLoadRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(RentalVehicleLoad $load): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_vehicle_loads (id, rental_id, rental_issued_item_id, vehicle_id, created_at)
             VALUES (:id, :rental_id, :rental_issued_item_id, :vehicle_id, :created_at)
             ON DUPLICATE KEY UPDATE vehicle_id = :vehicle_id2'
        );

        $stmt->execute([
            'id' => $load->id,
            'rental_id' => $load->rentalId,
            'rental_issued_item_id' => $load->rentalIssuedItemId,
            'vehicle_id' => $load->vehicleId,
            'created_at' => $load->createdAt->format('Y-m-d H:i:s'),
            'vehicle_id2' => $load->vehicleId,
        ]);
    }

    public function findById(string $id): ?RentalVehicleLoad
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_vehicle_loads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_vehicle_loads WHERE rental_id = :rental_id');
        $stmt->execute(['rental_id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByRentalIssuedItem(string $rentalIssuedItemId): ?RentalVehicleLoad
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_vehicle_loads WHERE rental_issued_item_id = :id');
        $stmt->execute(['id' => $rentalIssuedItemId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByVehicle(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_vehicle_loads WHERE vehicle_id = :vehicle_id');
        $stmt->execute(['vehicle_id' => $vehicleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_vehicle_loads WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteByRentalIssuedItem(string $rentalIssuedItemId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_vehicle_loads WHERE rental_issued_item_id = :id');
        $stmt->execute(['id' => $rentalIssuedItemId]);
    }

    private function hydrate(array $row): RentalVehicleLoad
    {
        return new RentalVehicleLoad(
            id: $row['id'],
            rentalId: $row['rental_id'],
            rentalIssuedItemId: $row['rental_issued_item_id'],
            vehicleId: $row['vehicle_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
