<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\DocumentStorage\DocumentStorageUploadLogEntry;
use App\Domain\DocumentStorage\DocumentStorageUploadLogRepositoryInterface;
use App\Domain\DocumentStorage\DocumentType;
use App\Domain\DocumentStorage\UploadStatus;
use DateTimeImmutable;
use PDO;

final class MariadbDocumentStorageUploadLogRepository implements DocumentStorageUploadLogRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(DocumentStorageUploadLogEntry $entry): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_storage_upload_log (
                id, document_type, source_id, assignment_id, storage_target_id, remote_path, status, error_message, created_at
             ) VALUES (
                :id, :document_type, :source_id, :assignment_id, :storage_target_id, :remote_path, :status, :error_message, :created_at
             )'
        );

        $stmt->execute([
            'id' => $entry->id,
            'document_type' => $entry->documentType->value,
            'source_id' => $entry->sourceId,
            'assignment_id' => $entry->assignmentId,
            'storage_target_id' => $entry->storageTargetId,
            'remote_path' => $entry->remotePath,
            'status' => $entry->status->value,
            'error_message' => $entry->errorMessage,
            'created_at' => $entry->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findRecent(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_storage_upload_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findForDocument(DocumentType $documentType, string $sourceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_storage_upload_log WHERE document_type = :document_type AND source_id = :source_id ORDER BY created_at DESC'
        );
        $stmt->execute(['document_type' => $documentType->value, 'source_id' => $sourceId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    private function hydrate(array $row): DocumentStorageUploadLogEntry
    {
        return new DocumentStorageUploadLogEntry(
            id: $row['id'],
            documentType: DocumentType::from($row['document_type']),
            sourceId: $row['source_id'],
            assignmentId: $row['assignment_id'],
            storageTargetId: $row['storage_target_id'],
            remotePath: $row['remote_path'],
            status: UploadStatus::from($row['status']),
            errorMessage: $row['error_message'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
