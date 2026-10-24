<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Vehicle\RentalVehicleTow;
use App\Domain\Vehicle\RentalVehicleTowRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalVehicleTowRepository implements RentalVehicleTowRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(RentalVehicleTow $tow): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_vehicle_tows (id, rental_id, towed_vehicle_id, towing_vehicle_id, created_at)
             VALUES (:id, :rental_id, :towed_vehicle_id, :towing_vehicle_id, :created_at)
             ON DUPLICATE KEY UPDATE towing_vehicle_id = :towing_vehicle_id2'
        );

        $stmt->execute([
            'id' => $tow->id,
            'rental_id' => $tow->rentalId,
            'towed_vehicle_id' => $tow->towedVehicleId,
            'towing_vehicle_id' => $tow->towingVehicleId,
            'created_at' => $tow->createdAt->format('Y-m-d H:i:s'),
            'towing_vehicle_id2' => $tow->towingVehicleId,
        ]);
    }

    public function findById(string $id): ?RentalVehicleTow
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_vehicle_tows WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_vehicle_tows WHERE rental_id = :rental_id');
        $stmt->execute(['rental_id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByRentalAndTowedVehicle(string $rentalId, string $towedVehicleId): ?RentalVehicleTow
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_vehicle_tows WHERE rental_id = :rental_id AND towed_vehicle_id = :towed_vehicle_id');
        $stmt->execute(['rental_id' => $rentalId, 'towed_vehicle_id' => $towedVehicleId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByVehicle(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_vehicle_tows WHERE towed_vehicle_id = :id OR towing_vehicle_id = :id2');
        $stmt->execute(['id' => $vehicleId, 'id2' => $vehicleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_vehicle_tows WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteByRentalAndTowedVehicle(string $rentalId, string $towedVehicleId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_vehicle_tows WHERE rental_id = :rental_id AND towed_vehicle_id = :towed_vehicle_id');
        $stmt->execute(['rental_id' => $rentalId, 'towed_vehicle_id' => $towedVehicleId]);
    }

    private function hydrate(array $row): RentalVehicleTow
    {
        return new RentalVehicleTow(
            id: $row['id'],
            rentalId: $row['rental_id'],
            towedVehicleId: $row['towed_vehicle_id'],
            towingVehicleId: $row['towing_vehicle_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
