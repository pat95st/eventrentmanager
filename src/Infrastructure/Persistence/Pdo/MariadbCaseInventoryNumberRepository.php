<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\CaseInventoryNumber\CaseInventoryNumber;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbCaseInventoryNumberRepository implements CaseInventoryNumberRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(CaseInventoryNumber $caseInventoryNumber): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_inventory_numbers (id, case_id, number, acquisition_date, created_at, updated_at)
             VALUES (:id, :case_id, :number, :acquisition_date, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE number = :number2, acquisition_date = :acquisition_date2, updated_at = :updated_at2'
        );

        $acquisitionDate = $caseInventoryNumber->acquisitionDate?->format('Y-m-d');

        $stmt->execute([
            'id' => $caseInventoryNumber->id,
            'case_id' => $caseInventoryNumber->caseId,
            'number' => $caseInventoryNumber->number,
            'acquisition_date' => $acquisitionDate,
            'created_at' => $caseInventoryNumber->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $caseInventoryNumber->updatedAt->format('Y-m-d H:i:s'),
            'number2' => $caseInventoryNumber->number,
            'acquisition_date2' => $acquisitionDate,
            'updated_at2' => $caseInventoryNumber->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?CaseInventoryNumber
    {
        $stmt = $this->pdo->prepare('SELECT * FROM case_inventory_numbers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCase(string $caseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM case_inventory_numbers WHERE case_id = :case_id ORDER BY number ASC'
        );
        $stmt->execute(['case_id' => $caseId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function existsByNumber(string $number, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM case_inventory_numbers WHERE number = :number';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['number' => $number];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM case_inventory_numbers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): CaseInventoryNumber
    {
        return new CaseInventoryNumber(
            id: $row['id'],
            caseId: $row['case_id'],
            number: $row['number'],
            acquisitionDate: $row['acquisition_date'] !== null ? new DateTimeImmutable($row['acquisition_date']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
