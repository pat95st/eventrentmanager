<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Article\ArticlePreIssueCheck;
use App\Domain\Article\ArticlePreIssueCheckRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbArticlePreIssueCheckRepository implements ArticlePreIssueCheckRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ArticlePreIssueCheck $check): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_pre_issue_checks (id, article_id, description, created_at, updated_at)
             VALUES (:id, :article_id, :description, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE description = :description2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $check->id,
            'article_id' => $check->articleId,
            'description' => $check->description,
            'created_at' => $check->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $check->updatedAt->format('Y-m-d H:i:s'),
            'description2' => $check->description,
            'updated_at2' => $check->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ArticlePreIssueCheck
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_pre_issue_checks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM article_pre_issue_checks WHERE article_id = :article_id ORDER BY description ASC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM article_pre_issue_checks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): ArticlePreIssueCheck
    {
        return new ArticlePreIssueCheck(
            id: $row['id'],
            articleId: $row['article_id'],
            description: $row['description'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
