<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rma\RmaDocument;
use App\Domain\Rma\RmaDocumentRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRmaDocumentRepository implements RmaDocumentRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(RmaDocument $document): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rma_documents
                (id, rma_id, original_filename, stored_filename, mime_type, size_bytes, uploaded_at)
             VALUES
                (:id, :rma_id, :original_filename, :stored_filename, :mime_type, :size_bytes, :uploaded_at)'
        );

        $stmt->execute([
            'id' => $document->id,
            'rma_id' => $document->rmaId,
            'original_filename' => $document->originalFilename,
            'stored_filename' => $document->storedFilename,
            'mime_type' => $document->mimeType,
            'size_bytes' => $document->sizeBytes,
            'uploaded_at' => $document->uploadedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?RmaDocument
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rma_documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRma(string $rmaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rma_documents WHERE rma_id = :rma_id ORDER BY uploaded_at ASC'
        );
        $stmt->execute(['rma_id' => $rmaId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByInventoryNumber(string $inventoryNumberId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.* FROM rma_documents d
             INNER JOIN rmas r ON r.id = d.rma_id
             WHERE r.inventory_number_id = :inventory_number_id
             ORDER BY d.uploaded_at DESC'
        );
        $stmt->execute(['inventory_number_id' => $inventoryNumberId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rma_documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): RmaDocument
    {
        return new RmaDocument(
            id: $row['id'],
            rmaId: $row['rma_id'],
            originalFilename: $row['original_filename'],
            storedFilename: $row['stored_filename'],
            mimeType: $row['mime_type'],
            sizeBytes: (int) $row['size_bytes'],
            uploadedAt: new DateTimeImmutable($row['uploaded_at']),
        );
    }
}
