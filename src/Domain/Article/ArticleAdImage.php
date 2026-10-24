<?php

declare(strict_types=1);

namespace App\Domain\Article;

use DateTimeImmutable;

/**
 * Ein von der KI (OpenAI gpt-image-1) generiertes Werbebild für einen
 * Artikel (z. B. für Instagram) — siehe
 * App\Application\Article\Command\GenerateArticleAdImageHandler. Anders als
 * ArticleImage (die eigentlichen, hochgeladenen Produktfotos) wird hier
 * bewusst eine VOLLSTÄNDIGE HISTORIE geführt (kein Überschreiben/Ersetzen),
 * damit frühere Varianten erhalten bleiben und erneut heruntergeladen
 * werden können (siehe Projekt-Entscheidung: neuer Galerie-Bereich mit
 * voller Historie je Artikel).
 */
final class ArticleAdImage
{
    public function __construct(
        public readonly string $id,
        public readonly string $articleId,
        public readonly string $filePath, // relativ zu storage/uploads/article-ad-images/
        public readonly ArticleAdImageFormat $format,
        public readonly string $prompt, // der tatsächlich an OpenAI gesendete Prompt, zur Nachvollziehbarkeit
        public readonly ?string $sourceArticleImageId, // ID des als Referenzbild genutzten ArticleImage, falls vorhanden
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
