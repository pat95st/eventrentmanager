<?php

declare(strict_types=1);

namespace App\Domain\Article;

/**
 * Bestandteil eines Bundle-Artikels (Migration 107), z. B. "2× passiver
 * Lautsprecher" im Bundle "Lautsprecher-Set mit Endstufe". Mengen gelten je
 * EIN Bundle.
 */
final class ArticleBundleComponent
{
    public function __construct(
        public readonly string $id,
        public readonly string $bundleArticleId,
        public readonly string $componentArticleId,
        public int $quantity,
        public int $sortOrder = 0,
    ) {
    }
}
