<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\NoticeDocument\NoticeDocumentArticleRepositoryInterface;
use PDO;

final class MariadbNoticeDocumentArticleRepository implements NoticeDocumentArticleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findArticleIdsByDocument(string $noticeDocumentId): array
    {
        $stmt = $this->pdo->prepare('SELECT article_id FROM notice_document_articles WHERE notice_document_id = :notice_document_id');
        $stmt->execute(['notice_document_id' => $noticeDocumentId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findDocumentIdsByArticleIds(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT notice_document_id FROM notice_document_articles WHERE article_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($articleIds));

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function replaceAssignments(string $noticeDocumentId, array $articleIds): void
    {
        $this->deleteByDocument($noticeDocumentId);

        $stmt = $this->pdo->prepare(
            'INSERT INTO notice_document_articles (notice_document_id, article_id) VALUES (:notice_document_id, :article_id)'
        );
        foreach (array_unique($articleIds) as $articleId) {
            $stmt->execute(['notice_document_id' => $noticeDocumentId, 'article_id' => $articleId]);
        }
    }

    public function deleteByDocument(string $noticeDocumentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM notice_document_articles WHERE notice_document_id = :notice_document_id');
        $stmt->execute(['notice_document_id' => $noticeDocumentId]);
    }

    public function removeAssignment(string $noticeDocumentId, string $articleId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM notice_document_articles WHERE notice_document_id = :notice_document_id AND article_id = :article_id'
        );
        $stmt->execute(['notice_document_id' => $noticeDocumentId, 'article_id' => $articleId]);
    }
}
