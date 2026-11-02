<?php

declare(strict_types=1);

namespace App\Domain\ArticleSet;

/** Ein Artikel oder Bundle mit fester Menge in einem Set. */
final class ArticleSetItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $setId,
        public readonly string $articleId,
        public int $quantity,
        public int $sortOrder = 0,
    ) {
    }
}
