<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\LaborTime\LaborTimeArticle;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbLaborTimeArticleRepository implements LaborTimeArticleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(LaborTimeArticle $laborTimeArticle): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO labor_time_articles (id, name, hourly_rate, created_at, updated_at)
             VALUES (:id, :name, :hourly_rate, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = :name2, hourly_rate = :hourly_rate2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $laborTimeArticle->id,
            'name' => $laborTimeArticle->name,
            'hourly_rate' => $laborTimeArticle->hourlyRate,
            'created_at' => $laborTimeArticle->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $laborTimeArticle->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $laborTimeArticle->name,
            'hourly_rate2' => $laborTimeArticle->hourlyRate,
            'updated_at2' => $laborTimeArticle->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?LaborTimeArticle
    {
        $stmt = $this->pdo->prepare('SELECT * FROM labor_time_articles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM labor_time_articles ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function existsByName(string $name, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM labor_time_articles WHERE name = :name';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['name' => $name];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isUsedByOfferItems(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM offer_labor_items WHERE labor_time_article_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isUsedByInvoiceItems(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM invoice_labor_items WHERE labor_time_article_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM labor_time_articles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): LaborTimeArticle
    {
        return new LaborTimeArticle(
            id: $row['id'],
            name: $row['name'],
            hourlyRate: $row['hourly_rate'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
