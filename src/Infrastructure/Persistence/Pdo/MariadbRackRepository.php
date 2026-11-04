<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rack\FixedRack;
use App\Domain\Rack\Rack;
use App\Domain\Rack\RackExemplar;
use App\Domain\Rack\RackRailType;
use App\Domain\Rack\RackRepositoryInterface;
use App\Domain\Rack\RackSlot;
use App\Domain\Rack\RackWidth;
use DateTimeImmutable;
use PDO;

/** Rack-Typen, Rack-Exemplare und Bestückung (Migrationen 115/124). */
final class MariadbRackRepository implements RackRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Rack $rack): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO racks (id, name, prefix, manufacturer_id, height_units, install_depth_cm, rail_type, width, color,
                has_shock_mount, has_wheels, description, created_at, updated_at)
             VALUES (:id, :name, :prefix, :manufacturer_id, :height_units, :install_depth_cm, :rail_type, :width, :color,
                :has_shock_mount, :has_wheels, :description, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = VALUES(name), prefix = VALUES(prefix), manufacturer_id = VALUES(manufacturer_id),
                height_units = VALUES(height_units), install_depth_cm = VALUES(install_depth_cm), rail_type = VALUES(rail_type),
                width = VALUES(width), color = VALUES(color), has_shock_mount = VALUES(has_shock_mount), has_wheels = VALUES(has_wheels),
                description = VALUES(description), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $rack->id,
            'name' => $rack->name,
            'prefix' => $rack->prefix,
            'manufacturer_id' => $rack->manufacturerId,
            'height_units' => $rack->heightUnits,
            'install_depth_cm' => $rack->installDepthCm,
            'rail_type' => $rack->railType->value,
            'width' => $rack->width->value,
            'color' => $rack->color,
            'has_shock_mount' => $rack->hasShockMount ? 1 : 0,
            'has_wheels' => $rack->hasWheels ? 1 : 0,
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
            $stmt = $this->pdo->query('SELECT * FROM racks ORDER BY name');
        } else {
            // Suche auch nach Rack- und Inventurnummern der Exemplare.
            $stmt = $this->pdo->prepare(
                'SELECT * FROM racks r WHERE r.name LIKE :q1 OR r.prefix LIKE :q2 OR r.color LIKE :q3
                    OR EXISTS (SELECT 1 FROM rack_exemplars e WHERE e.rack_id = r.id AND (e.rack_number LIKE :q4 OR e.inventory_number LIKE :q5))
                 ORDER BY r.name'
            );
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like]);
        }

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM racks WHERE id = :id')->execute(['id' => $id]);
    }

    public function existsByPrefix(string $manufacturerId, string $prefix, ?string $excludeId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM racks WHERE manufacturer_id = :manufacturer_id AND prefix = :prefix AND id <> :exclude');
        $stmt->execute(['manufacturer_id' => $manufacturerId, 'prefix' => $prefix, 'exclude' => $excludeId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function saveExemplar(RackExemplar $exemplar): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rack_exemplars (id, rack_id, rack_number, inventory_number, fixed_inventory_number_id, acquisition_date, created_at, updated_at)
             VALUES (:id, :rack_id, :rack_number, :inventory_number, :fixed_inventory_number_id, :acquisition_date, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE rack_number = VALUES(rack_number), inventory_number = VALUES(inventory_number),
                fixed_inventory_number_id = VALUES(fixed_inventory_number_id), acquisition_date = VALUES(acquisition_date),
                updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $exemplar->id,
            'rack_id' => $exemplar->rackId,
            'rack_number' => $exemplar->rackNumber,
            'inventory_number' => $exemplar->inventoryNumber,
            'fixed_inventory_number_id' => $exemplar->fixedInventoryNumberId,
            'acquisition_date' => $exemplar->acquisitionDate?->format('Y-m-d'),
            'created_at' => $exemplar->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $exemplar->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findExemplarById(string $id): ?RackExemplar
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rack_exemplars WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateExemplar($row) : null;
    }

    public function findExemplars(string $rackId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rack_exemplars WHERE rack_id = :rack_id ORDER BY rack_number, inventory_number');
        $stmt->execute(['rack_id' => $rackId]);

        return array_map($this->hydrateExemplar(...), $stmt->fetchAll());
    }

    public function deleteExemplar(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rack_exemplars WHERE id = :id')->execute(['id' => $id]);
    }

    public function existsExemplarByInventoryNumber(string $number, ?string $excludeId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rack_exemplars WHERE inventory_number = :number AND id <> :exclude');
        $stmt->execute(['number' => $number, 'exclude' => $excludeId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsExemplarByRackNumber(string $number, ?string $excludeId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rack_exemplars WHERE rack_number = :number AND id <> :exclude');
        $stmt->execute(['number' => $number, 'exclude' => $excludeId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findExemplarByFixedInventoryNumber(string $inventoryNumberId): ?RackExemplar
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rack_exemplars WHERE fixed_inventory_number_id = :id');
        $stmt->execute(['id' => $inventoryNumberId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateExemplar($row) : null;
    }

    public function findFixedRacks(array $inventoryNumberIds): array
    {
        $inventoryNumberIds = array_values(array_unique(array_filter($inventoryNumberIds)));
        if ($inventoryNumberIds === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($inventoryNumberIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.rack_id, e.rack_number, e.inventory_number, e.fixed_inventory_number_id, r.name
             FROM rack_exemplars e JOIN racks r ON r.id = e.rack_id
             WHERE e.fixed_inventory_number_id IN ($placeholders)"
        );
        $stmt->execute($inventoryNumberIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['fixed_inventory_number_id']] = new FixedRack($row['id'], $row['rack_id'], $row['name'], (string) $row['rack_number'], $row['inventory_number']);
        }

        return $result;
    }

    public function findSlots(string $exemplarId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rack_slots WHERE rack_exemplar_id = :id ORDER BY start_unit');
        $stmt->execute(['id' => $exemplarId]);

        return array_map($this->hydrateSlot(...), $stmt->fetchAll());
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
            'INSERT INTO rack_slots (id, rack_exemplar_id, inventory_number_id, start_unit, created_at)
             VALUES (:id, :rack_exemplar_id, :inventory_number_id, :start_unit, :created_at)
             ON DUPLICATE KEY UPDATE start_unit = VALUES(start_unit)'
        );
        $stmt->execute([
            'id' => $slot->id,
            'rack_exemplar_id' => $slot->exemplarId,
            'inventory_number_id' => $slot->inventoryNumberId,
            'start_unit' => $slot->startUnit,
            'created_at' => $slot->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteSlot(string $slotId): void
    {
        $this->pdo->prepare('DELETE FROM rack_slots WHERE id = :id')->execute(['id' => $slotId]);
    }

    private function hydrate(array $row): Rack
    {
        return new Rack(
            id: $row['id'],
            name: $row['name'],
            prefix: $row['prefix'] ?? null,
            manufacturerId: $row['manufacturer_id'] ?? null,
            heightUnits: (int) $row['height_units'],
            installDepthCm: $row['install_depth_cm'] !== null ? (string) $row['install_depth_cm'] : null,
            railType: RackRailType::tryFrom((string) $row['rail_type']) ?? RackRailType::Front,
            width: RackWidth::tryFrom((string) $row['width']) ?? RackWidth::Full,
            color: $row['color'] ?? null,
            hasShockMount: (bool) $row['has_shock_mount'],
            hasWheels: (bool) $row['has_wheels'],
            description: $row['description'] ?? null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    private function hydrateExemplar(array $row): RackExemplar
    {
        return new RackExemplar(
            id: $row['id'],
            rackId: $row['rack_id'],
            rackNumber: (string) ($row['rack_number'] ?? ''),
            inventoryNumber: $row['inventory_number'],
            fixedInventoryNumberId: $row['fixed_inventory_number_id'] ?? null,
            acquisitionDate: ($row['acquisition_date'] ?? null) !== null ? new DateTimeImmutable($row['acquisition_date']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    private function hydrateSlot(array $row): RackSlot
    {
        return new RackSlot(
            id: $row['id'],
            exemplarId: $row['rack_exemplar_id'],
            inventoryNumberId: $row['inventory_number_id'],
            startUnit: (int) $row['start_unit'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
