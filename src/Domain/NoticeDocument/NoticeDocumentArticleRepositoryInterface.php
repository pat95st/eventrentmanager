<?php

declare(strict_types=1);

namespace App\Domain\NoticeDocument;

/**
 * Verwaltet die n:m-Zuordnung zwischen Hinweisdokumenten und Artikeln
 * (Tabelle notice_document_articles). Bewusst ein eigenes, schlankes
 * Repository statt einer Liste von Artikel-IDs direkt auf NoticeDocument —
 * analog ArticleAccessoryRepositoryInterface für Zubehör-Zuordnungen.
 */
interface NoticeDocumentArticleRepositoryInterface
{
    /** @return string[] Artikel-IDs, denen dieses Dokument zugeordnet ist. */
    public function findArticleIdsByDocument(string $noticeDocumentId): array;

    /**
     * @param string[] $articleIds
     * @return string[] Eindeutige Dokument-IDs, die mind. einem der übergebenen Artikel zugeordnet sind.
     */
    public function findDocumentIdsByArticleIds(array $articleIds): array;

    /** Ersetzt ALLE Zuordnungen dieses Dokuments durch die übergebene Artikel-Liste (z. B. aus einem Formular mit Checkboxen). @param string[] $articleIds */
    public function replaceAssignments(string $noticeDocumentId, array $articleIds): void;

    /** Entfernt alle Zuordnungen dieses Dokuments (z. B. vor dem Löschen des Dokuments selbst). */
    public function deleteByDocument(string $noticeDocumentId): void;

    /**
     * Entfernt NUR die Zuordnung zu einem einzelnen Artikel (z. B. "von diesem
     * Artikel entfernen" direkt auf der Artikel-Detailseite) — das Dokument
     * selbst und etwaige Zuordnungen zu anderen Artikeln bleiben erhalten.
     */
    public function removeAssignment(string $noticeDocumentId, string $articleId): void;
}
