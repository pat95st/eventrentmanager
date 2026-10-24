<?php

declare(strict_types=1);

namespace App\Domain\Article;

use DateTimeImmutable;

/**
 * Zubehör zu einem Artikel ohne eigene Inventurnummer (z. B. Kabel, Adapter,
 * Tasche) — nur Anzahl und Bezeichnung, keine eigene Nachverfolgung per
 * Barcode/Inventurnummer.
 */
final class ArticleAccessory
{
    public function __construct(
        public readonly string $id,
        public readonly string $articleId,
        public int $quantity,
        public string $description,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
