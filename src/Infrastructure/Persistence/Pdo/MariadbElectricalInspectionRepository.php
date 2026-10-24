<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\ElectricalInspection\ElectricalInspection;
use App\Domain\ElectricalInspection\ElectricalInspectionRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbElectricalInspectionRepository implements ElectricalInspectionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ElectricalInspection $inspection): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO electrical_inspections
                (id, inventory_number_id, inspected_at, validity_years, passed, inspector, notes, created_at, updated_at)
             VALUES
                (:id, :inventory_number_id, :inspected_at, :validity_years, :passed, :inspector, :notes, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                inspected_at = :inspected_at2, validity_years = :validity_years2, passed = :passed2,
                inspector = :inspector2, notes = :notes2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $inspection->id,
            'inventory_number_id' => $inspection->inventoryNumberId,
            'inspected_at' => $inspection->inspectedAt->format('Y-m-d'),
            'validity_years' => $inspection->validityYears,
            'passed' => (int) $inspection->passed,
            'inspector' => $inspection->inspector,
            'notes' => $inspection->notes,
            'created_at' => $inspection->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $inspection->updatedAt->format('Y-m-d H:i:s'),
            'inspected_at2' => $inspection->inspectedAt->format('Y-m-d'),
            'validity_years2' => $inspection->validityYears,
            'passed2' => (int) $inspection->passed,
            'inspector2' => $inspection->inspector,
            'notes2' => $inspection->notes,
            'updated_at2' => $inspection->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ElectricalInspection
    {
        $stmt = $this->pdo->prepare('SELECT * FROM electrical_inspections WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByInventoryNumber(string $inventoryNumberId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM electrical_inspections WHERE inventory_number_id = :inventory_number_id
             ORDER BY inspected_at DESC, created_at DESC'
        );
        $stmt->execute(['inventory_number_id' => $inventoryNumberId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findLatestByInventoryNumber(string $inventoryNumberId): ?ElectricalInspection
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM electrical_inspections WHERE inventory_number_id = :inventory_number_id
             ORDER BY inspected_at DESC, created_at DESC LIMIT 1'
        );
        $stmt->execute(['inventory_number_id' => $inventoryNumberId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function countByInventoryNumber(string $inventoryNumberId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM electrical_inspections WHERE inventory_number_id = :inventory_number_id');
        $stmt->execute(['inventory_number_id' => $inventoryNumberId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM electrical_inspections WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): ElectricalInspection
    {
        return new ElectricalInspection(
            id: $row['id'],
            inventoryNumberId: $row['inventory_number_id'],
            inspectedAt: new DateTimeImmutable($row['inspected_at']),
            validityYears: (int) $row['validity_years'],
            passed: (bool) $row['passed'],
            inspector: $row['inspector'],
            notes: $row['notes'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
