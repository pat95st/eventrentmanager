<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\DocumentStorage\DocumentStorageAssignment;
use App\Domain\DocumentStorage\DocumentStorageAssignmentRepositoryInterface;
use App\Domain\DocumentStorage\DocumentType;
use DateTimeImmutable;
use PDO;

final class MariadbDocumentStorageAssignmentRepository implements DocumentStorageAssignmentRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(DocumentStorageAssignment $assignment): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_storage_assignments (
                id, document_type, storage_target_id, target_path, enabled, created_at
             ) VALUES (
                :id, :document_type, :storage_target_id, :target_path, :enabled, :created_at
             )
             ON DUPLICATE KEY UPDATE
                document_type = VALUES(document_type), storage_target_id = VALUES(storage_target_id),
                target_path = VALUES(target_path), enabled = VALUES(enabled)'
        );

        $stmt->execute([
            'id' => $assignment->id,
            'document_type' => $assignment->documentType->value,
            'storage_target_id' => $assignment->storageTargetId,
            'target_path' => $assignment->targetPath,
            'enabled' => $assignment->enabled ? 1 : 0,
            'created_at' => $assignment->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?DocumentStorageAssignment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_storage_assignments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM document_storage_assignments ORDER BY document_type ASC, created_at ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findEnabledForDocumentType(DocumentType $documentType): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_storage_assignments WHERE document_type = :document_type AND enabled = 1 ORDER BY created_at ASC'
        );
        $stmt->execute(['document_type' => $documentType->value]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM document_storage_assignments WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): DocumentStorageAssignment
    {
        return new DocumentStorageAssignment(
            id: $row['id'],
            documentType: DocumentType::from($row['document_type']),
            storageTargetId: $row['storage_target_id'],
            targetPath: $row['target_path'],
            enabled: (bool) $row['enabled'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
