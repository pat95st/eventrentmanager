<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbVehicleRepository implements VehicleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Vehicle $vehicle): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO vehicles (
                id, category, name, license_plate, payload_kg,
                load_area_length_cm, load_area_width_cm, load_area_height_cm, load_area_open_top,
                tow_hitch_available, tow_capacity_kg, empty_weight_kg, notes, created_at, updated_at
             ) VALUES (
                :id, :category, :name, :license_plate, :payload_kg,
                :load_area_length_cm, :load_area_width_cm, :load_area_height_cm, :load_area_open_top,
                :tow_hitch_available, :tow_capacity_kg, :empty_weight_kg, :notes, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                category = :category2, name = :name2, license_plate = :license_plate2,
                payload_kg = :payload_kg2,
                load_area_length_cm = :load_area_length_cm2, load_area_width_cm = :load_area_width_cm2,
                load_area_height_cm = :load_area_height_cm2, load_area_open_top = :load_area_open_top2,
                tow_hitch_available = :tow_hitch_available2, tow_capacity_kg = :tow_capacity_kg2,
                empty_weight_kg = :empty_weight_kg2, notes = :notes2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $vehicle->id,
            'category' => $vehicle->category->value,
            'name' => $vehicle->name,
            'license_plate' => $vehicle->licensePlate,
            'payload_kg' => $vehicle->payloadKg,
            'load_area_length_cm' => $vehicle->loadAreaLengthCm,
            'load_area_width_cm' => $vehicle->loadAreaWidthCm,
            'load_area_height_cm' => $vehicle->loadAreaHeightCm,
            'load_area_open_top' => (int) $vehicle->loadAreaOpenTop,
            'tow_hitch_available' => $vehicle->towHitchAvailable === null ? null : (int) $vehicle->towHitchAvailable,
            'tow_capacity_kg' => $vehicle->towCapacityKg,
            'empty_weight_kg' => $vehicle->emptyWeightKg,
            'notes' => $vehicle->notes,
            'created_at' => $vehicle->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $vehicle->updatedAt->format('Y-m-d H:i:s'),
            'category2' => $vehicle->category->value,
            'name2' => $vehicle->name,
            'license_plate2' => $vehicle->licensePlate,
            'payload_kg2' => $vehicle->payloadKg,
            'load_area_length_cm2' => $vehicle->loadAreaLengthCm,
            'load_area_width_cm2' => $vehicle->loadAreaWidthCm,
            'load_area_height_cm2' => $vehicle->loadAreaHeightCm,
            'load_area_open_top2' => (int) $vehicle->loadAreaOpenTop,
            'tow_hitch_available2' => $vehicle->towHitchAvailable === null ? null : (int) $vehicle->towHitchAvailable,
            'tow_capacity_kg2' => $vehicle->towCapacityKg,
            'empty_weight_kg2' => $vehicle->emptyWeightKg,
            'notes2' => $vehicle->notes,
            'updated_at2' => $vehicle->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Vehicle
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vehicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $sql = 'SELECT * FROM vehicles' . $where . ' ORDER BY name ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
            $stmt->bindValue('offset', max(0, $offset ?? 0), PDO::PARAM_INT);
        }
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByCategory(VehicleCategory $category): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vehicles WHERE category = :category ORDER BY name ASC');
        $stmt->execute(['category' => $category->value]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countAll(?string $search = null): int
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vehicles' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return ['', []];
        }

        $needle = '%' . $search . '%';

        return [' WHERE name LIKE :search OR license_plate LIKE :search2', ['search' => $needle, 'search2' => $needle]];
    }

    public function isUsedByVehicleLoads(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rental_vehicle_loads WHERE vehicle_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isUsedByVehicleTows(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rental_vehicle_tows WHERE towed_vehicle_id = :id OR towing_vehicle_id = :id2');
        $stmt->execute(['id' => $id, 'id2' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vehicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Vehicle
    {
        return new Vehicle(
            id: $row['id'],
            category: VehicleCategory::from($row['category']),
            name: $row['name'],
            licensePlate: $row['license_plate'],
            payloadKg: $row['payload_kg'],
            loadAreaLengthCm: $row['load_area_length_cm'],
            loadAreaWidthCm: $row['load_area_width_cm'],
            loadAreaHeightCm: $row['load_area_height_cm'],
            loadAreaOpenTop: (bool) $row['load_area_open_top'],
            towHitchAvailable: $row['tow_hitch_available'] === null ? null : (bool) $row['tow_hitch_available'],
            towCapacityKg: $row['tow_capacity_kg'],
            emptyWeightKg: $row['empty_weight_kg'],
            notes: $row['notes'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
