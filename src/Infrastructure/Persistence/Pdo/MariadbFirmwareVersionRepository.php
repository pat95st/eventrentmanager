<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Article\FirmwareVersion;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbFirmwareVersionRepository implements FirmwareVersionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(FirmwareVersion $firmwareVersion): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO firmware_versions (id, article_id, version, released_at, created_at)
             VALUES (:id, :article_id, :version, :released_at, :created_at)
             ON DUPLICATE KEY UPDATE version = :version2, released_at = :released_at2'
        );

        $stmt->execute([
            'id' => $firmwareVersion->id,
            'article_id' => $firmwareVersion->articleId,
            'version' => $firmwareVersion->version,
            'released_at' => $firmwareVersion->releasedAt?->format('Y-m-d'),
            'created_at' => $firmwareVersion->createdAt->format('Y-m-d H:i:s'),
            'version2' => $firmwareVersion->version,
            'released_at2' => $firmwareVersion->releasedAt?->format('Y-m-d'),
        ]);
    }

    public function findById(string $id): ?FirmwareVersion
    {
        $stmt = $this->pdo->prepare('SELECT * FROM firmware_versions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAllForArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM firmware_versions WHERE article_id = :article_id
             ORDER BY (released_at IS NULL) ASC, released_at DESC, created_at DESC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findLatestForArticle(string $articleId): ?FirmwareVersion
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM firmware_versions WHERE article_id = :article_id
             ORDER BY (released_at IS NULL) ASC, released_at DESC, created_at DESC
             LIMIT 1'
        );
        $stmt->execute(['article_id' => $articleId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM firmware_versions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): FirmwareVersion
    {
        return new FirmwareVersion(
            id: $row['id'],
            articleId: $row['article_id'],
            version: $row['version'],
            releasedAt: $row['released_at'] !== null ? new DateTimeImmutable($row['released_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
