<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\NoticeDocument\NoticeDocument;
use App\Domain\NoticeDocument\NoticeDocumentRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbNoticeDocumentRepository implements NoticeDocumentRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(NoticeDocument $document): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notice_documents (
                id, name, original_filename, stored_filename, mime_type, size_bytes, created_at
             ) VALUES (
                :id, :name, :original_filename, :stored_filename, :mime_type, :size_bytes, :created_at
             )
             ON DUPLICATE KEY UPDATE name = :name2'
        );

        $stmt->execute([
            'id' => $document->id,
            'name' => $document->name,
            'original_filename' => $document->originalFilename,
            'stored_filename' => $document->storedFilename,
            'mime_type' => $document->mimeType,
            'size_bytes' => $document->sizeBytes,
            'created_at' => $document->createdAt->format('Y-m-d H:i:s'),
            'name2' => $document->name,
        ]);
    }

    public function findById(string $id): ?NoticeDocument
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notice_documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM notice_documents WHERE id IN ({$placeholders}) ORDER BY name ASC");
        $stmt->execute(array_values($ids));

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM notice_documents ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM notice_documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): NoticeDocument
    {
        return new NoticeDocument(
            id: $row['id'],
            name: $row['name'],
            originalFilename: $row['original_filename'],
            storedFilename: $row['stored_filename'],
            mimeType: $row['mime_type'],
            sizeBytes: (int) $row['size_bytes'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
