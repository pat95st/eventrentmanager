<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rack\Rack;
use App\Domain\Rack\RackRailType;
use App\Domain\Rack\RackRepositoryInterface;
use App\Domain\Rack\RackSlot;
use App\Domain\Rack\RackWidth;
use DateTimeImmutable;
use PDO;

final class MariadbRackRepository implements RackRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Rack $rack): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO racks (id, name, inventory_number, manufacturer_id, height_units, install_depth_cm, rail_type, width, color,
                has_shock_mount, has_wheels, fixed_inventory_number_id, description, created_at, updated_at)
             VALUES (:id, :name, :inventory_number, :manufacturer_id, :height_units, :install_depth_cm, :rail_type, :width, :color,
                :has_shock_mount, :has_wheels, :fixed_inventory_number_id, :description, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = VALUES(name), inventory_number = VALUES(inventory_number), manufacturer_id = VALUES(manufacturer_id),
                height_units = VALUES(height_units), install_depth_cm = VALUES(install_depth_cm), rail_type = VALUES(rail_type),
                width = VALUES(width), color = VALUES(color), has_shock_mount = VALUES(has_shock_mount), has_wheels = VALUES(has_wheels),
                fixed_inventory_number_id = VALUES(fixed_inventory_number_id), description = VALUES(description), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $rack->id,
            'name' => $rack->name,
            'inventory_number' => $rack->inventoryNumber,
            'manufacturer_id' => $rack->manufacturerId,
            'height_units' => $rack->heightUnits,
            'install_depth_cm' => $rack->installDepthCm,
            'rail_type' => $rack->railType->value,
            'width' => $rack->width->value,
            'color' => $rack->color,
            'has_shock_mount' => $rack->hasShockMount ? 1 : 0,
            'has_wheels' => $rack->hasWheels ? 1 : 0,
            'fixed_inventory_number_id' => $rack->fixedInventoryNumberId,
            'description' => $rack->description,
            'created_at' => $rack->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $rack->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Rack
    {
        $stmt = $this->pdo->prepare('SELECT * FROM racks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null): array
    {
        $search = $search !== null ? trim($search) : '';
        if ($search === '') {
            $stmt = $this->pdo->query('SELECT * FROM racks ORDER BY name, inventory_number');
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM racks WHERE name LIKE :q OR inventory_number LIKE :q2 OR color LIKE :q3 ORDER BY name, inventory_number');
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $stmt->execute(['q' => $like, 'q2' => $like, 'q3' => $like]);
        }

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM racks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function existsByInventoryNumber(string $number, ?string $excludeId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM racks WHERE inventory_number = :number AND id <> :exclude');
        $stmt->execute(['number' => $number, 'exclude' => $excludeId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findByFixedInventoryNumber(string $inventoryNumberId): ?Rack
    {
        $stmt = $this->pdo->prepare('SELECT * FROM racks WHERE fixed_inventory_number_id = :id');
        $stmt->execute(['id' => $inventoryNumberId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByFixedInventoryNumbers(array $inventoryNumberIds): array
    {
        $inventoryNumberIds = array_values(array_unique($inventoryNumberIds));
        if ($inventoryNumberIds === []) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach ($inventoryNumberIds as $i => $id) {
            $placeholders[] = ':id' . $i;
            $params['id' . $i] = $id;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM racks WHERE fixed_inventory_number_id IN (' . implode(', ', $placeholders) . ')');
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $rack = $this->hydrate($row);
            $result[(string) $rack->fixedInventoryNumberId] = $rack;
        }

        return $result;
    }

    public function findSlots(string $rackId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rack_slots WHERE rack_id = :id ORDER BY start_unit');
        $stmt->execute(['id' => $rackId]);

        return array_map(fn (array $row) => $this->hydrateSlot($row), $stmt->fetchAll());
    }

    public function findSlotById(string $slotId): ?RackSlot
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rack_slots WHERE id = :id');
        $stmt->execute(['id' => $slotId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateSlot($row) : null;
    }

    public function findSlotByInventoryNumber(string $inventoryNumberId): ?RackSlot
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rack_slots WHERE inventory_number_id = :id');
        $stmt->execute(['id' => $inventoryNumberId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateSlot($row) : null;
    }

    public function saveSlot(RackSlot $slot): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rack_slots (id, rack_id, inventory_number_id, start_unit, created_at)
             VALUES (:id, :rack_id, :inventory_number_id, :start_unit, :created_at)
             ON DUPLICATE KEY UPDATE start_unit = VALUES(start_unit)'
        );
        $stmt->execute([
            'id' => $slot->id,
            'rack_id' => $slot->rackId,
            'inventory_number_id' => $slot->inventoryNumberId,
            'start_unit' => $slot->startUnit,
            'created_at' => $slot->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteSlot(string $slotId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rack_slots WHERE id = :id');
        $stmt->execute(['id' => $slotId]);
    }

    private function hydrate(array $row): Rack
    {
        return new Rack(
            id: $row['id'],
            name: $row['name'],
            inventoryNumber: $row['inventory_number'],
            manufacturerId: $row['manufacturer_id'] ?? null,
            heightUnits: (int) $row['height_units'],
            installDepthCm: $row['install_depth_cm'] !== null ? (string) $row['install_depth_cm'] : null,
            railType: RackRailType::tryFrom((string) $row['rail_type']) ?? RackRailType::Front,
            width: RackWidth::tryFrom((string) $row['width']) ?? RackWidth::Full,
            color: $row['color'] ?? null,
            hasShockMount: (bool) $row['has_shock_mount'],
            hasWheels: (bool) $row['has_wheels'],
            fixedInventoryNumberId: $row['fixed_inventory_number_id'] ?? null,
            description: $row['description'] ?? null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    private function hydrateSlot(array $row): RackSlot
    {
        return new RackSlot(
            id: $row['id'],
            rackId: $row['rack_id'],
            inventoryNumberId: $row['inventory_number_id'],
            startUnit: (int) $row['start_unit'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
