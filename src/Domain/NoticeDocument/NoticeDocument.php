<?php

declare(strict_types=1);

namespace App\Domain\NoticeDocument;

use DateTimeImmutable;

/**
 * Ein generisches, einmal hochgeladenes Hinweisdokument (z. B. Bedienungs-
 * anleitung, Sicherheitshinweis, Einweisungsprotokoll), das bestimmten
 * Artikeln zugeordnet werden kann (siehe NoticeDocumentArticleRepositoryInterface).
 * Bucht ein Kunde einen Artikel, dem ein solches Dokument zugeordnet ist,
 * kann es ihm mitgeschickt werden (siehe GetNoticeDocumentsForArticles /
 * RentalController — Versand per E-Mail-Anhang, analog Packliste).
 */
final class NoticeDocument
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public readonly string $originalFilename,
        public readonly string $storedFilename,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
