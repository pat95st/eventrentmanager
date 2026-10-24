<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Article\ArticleAdImage;
use App\Domain\Article\ArticleAdImageFormat;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbArticleAdImageRepository implements ArticleAdImageRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ArticleAdImage $image): void
    {
        // Bewusst reines INSERT (kein ON DUPLICATE KEY UPDATE wie bei den
        // übrigen Artikel-Unterentitäten): jedes generierte Werbebild ist
        // ein eigener, unveränderlicher Historien-Eintrag (siehe
        // ArticleAdImage), es gibt kein "Bearbeiten" eines bestehenden Bilds.
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_ad_images (id, article_id, file_path, format, prompt, source_article_image_id, created_at)
             VALUES (:id, :article_id, :file_path, :format, :prompt, :source_article_image_id, :created_at)'
        );

        $stmt->execute([
            'id' => $image->id,
            'article_id' => $image->articleId,
            'file_path' => $image->filePath,
            'format' => $image->format->value,
            'prompt' => $image->prompt,
            'source_article_image_id' => $image->sourceArticleImageId,
            'created_at' => $image->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ArticleAdImage
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_ad_images WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM article_ad_images WHERE article_id = :article_id ORDER BY created_at DESC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM article_ad_images WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): ArticleAdImage
    {
        return new ArticleAdImage(
            id: $row['id'],
            articleId: $row['article_id'],
            filePath: $row['file_path'],
            format: ArticleAdImageFormat::from($row['format']),
            prompt: $row['prompt'],
            sourceArticleImageId: $row['source_article_image_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
