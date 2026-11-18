<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Routing\PlannedVehicle;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use PDO;

final class MariadbPlannedVehicleRepository implements PlannedVehicleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_planned_vehicles WHERE rental_id = :id ORDER BY created_at');
        $stmt->execute(['id' => $rentalId]);

        return array_map(static fn (array $r) => new PlannedVehicle((string) $r['id'], (string) $r['rental_id'], (string) $r['vehicle_id'], $r['towing_vehicle_id']), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function save(PlannedVehicle $vehicle): void
    {
        $this->pdo->prepare(
            'INSERT INTO rental_planned_vehicles (id, rental_id, vehicle_id, towing_vehicle_id) VALUES (:id, :rental, :vehicle, :towing)
             ON DUPLICATE KEY UPDATE towing_vehicle_id = VALUES(towing_vehicle_id)'
        )->execute(['id' => $vehicle->id, 'rental' => $vehicle->rentalId, 'vehicle' => $vehicle->vehicleId, 'towing' => $vehicle->towingVehicleId]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rental_planned_vehicles WHERE id = :id')->execute(['id' => $id]);
    }
}
