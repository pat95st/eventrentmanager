<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\CaseInventoryNumber\CaseInventoryNumberImage;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberImageRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbCaseInventoryNumberImageRepository implements CaseInventoryNumberImageRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByInventoryNumber(string $caseInventoryNumberId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM case_inventory_number_images WHERE case_inventory_number_id = :id ORDER BY position, created_at');
        $stmt->execute(['id' => $caseInventoryNumberId]);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findById(string $id): ?CaseInventoryNumberImage
    {
        $stmt = $this->pdo->prepare('SELECT * FROM case_inventory_number_images WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(CaseInventoryNumberImage $image): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_inventory_number_images (id, case_inventory_number_id, file_path, position, created_at)
             VALUES (:id, :case_inventory_number_id, :file_path, :position, :created_at)
             ON DUPLICATE KEY UPDATE position = :position2'
        );
        $stmt->execute([
            'id' => $image->id,
            'case_inventory_number_id' => $image->caseInventoryNumberId,
            'file_path' => $image->filePath,
            'position' => $image->position,
            'created_at' => $image->createdAt->format('Y-m-d H:i:s'),
            'position2' => $image->position,
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM case_inventory_number_images WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): CaseInventoryNumberImage
    {
        return new CaseInventoryNumberImage(
            id: $row['id'],
            caseInventoryNumberId: $row['case_inventory_number_id'],
            filePath: $row['file_path'],
            position: (int) $row['position'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
