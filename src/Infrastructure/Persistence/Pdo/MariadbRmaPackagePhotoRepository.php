<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rma\RmaPackagePhoto;
use App\Domain\Rma\RmaPackagePhotoRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRmaPackagePhotoRepository implements RmaPackagePhotoRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(RmaPackagePhoto $photo): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rma_package_photos
                (id, rma_id, original_filename, stored_filename, mime_type, size_bytes, uploaded_at)
             VALUES
                (:id, :rma_id, :original_filename, :stored_filename, :mime_type, :size_bytes, :uploaded_at)'
        );

        $stmt->execute([
            'id' => $photo->id,
            'rma_id' => $photo->rmaId,
            'original_filename' => $photo->originalFilename,
            'stored_filename' => $photo->storedFilename,
            'mime_type' => $photo->mimeType,
            'size_bytes' => $photo->sizeBytes,
            'uploaded_at' => $photo->uploadedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?RmaPackagePhoto
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rma_package_photos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRma(string $rmaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rma_package_photos WHERE rma_id = :rma_id ORDER BY uploaded_at ASC'
        );
        $stmt->execute(['rma_id' => $rmaId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rma_package_photos WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): RmaPackagePhoto
    {
        return new RmaPackagePhoto(
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
