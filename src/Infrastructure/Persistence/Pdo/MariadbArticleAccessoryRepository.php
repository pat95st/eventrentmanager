<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Article\ArticleAccessory;
use App\Domain\Article\ArticleAccessoryRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbArticleAccessoryRepository implements ArticleAccessoryRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ArticleAccessory $accessory): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_accessories (id, article_id, quantity, description, created_at, updated_at)
             VALUES (:id, :article_id, :quantity, :description, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE quantity = :quantity2, description = :description2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $accessory->id,
            'article_id' => $accessory->articleId,
            'quantity' => $accessory->quantity,
            'description' => $accessory->description,
            'created_at' => $accessory->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $accessory->updatedAt->format('Y-m-d H:i:s'),
            'quantity2' => $accessory->quantity,
            'description2' => $accessory->description,
            'updated_at2' => $accessory->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ArticleAccessory
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_accessories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM article_accessories WHERE article_id = :article_id ORDER BY description ASC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM article_accessories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): ArticleAccessory
    {
        return new ArticleAccessory(
            id: $row['id'],
            articleId: $row['article_id'],
            quantity: (int) $row['quantity'],
            description: $row['description'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
