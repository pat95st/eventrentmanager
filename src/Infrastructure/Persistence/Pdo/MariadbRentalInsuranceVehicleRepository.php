<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Insurance\RentalInsuranceVehicleRepositoryInterface;
use PDO;

final class MariadbRentalInsuranceVehicleRepository implements RentalInsuranceVehicleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function vehicleIdsForRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT vehicle_id FROM rental_insurance_vehicles WHERE rental_id = :rental_id ORDER BY created_at ASC');
        $stmt->execute(['rental_id' => $rentalId]);

        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function add(string $rentalId, string $vehicleId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO rental_insurance_vehicles (rental_id, vehicle_id, created_at) VALUES (:rental_id, :vehicle_id, NOW())');
        $stmt->execute(['rental_id' => $rentalId, 'vehicle_id' => $vehicleId]);
    }

    public function remove(string $rentalId, string $vehicleId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rental_insurance_vehicles WHERE rental_id = :rental_id AND vehicle_id = :vehicle_id');
        $stmt->execute(['rental_id' => $rentalId, 'vehicle_id' => $vehicleId]);
    }
}
