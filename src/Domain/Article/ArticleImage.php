<?php

declare(strict_types=1);

namespace App\Domain\Article;

use DateTimeImmutable;

final class ArticleImage
{
    public function __construct(
        public readonly string $id,
        public readonly string $articleId,
        public readonly string $filePath, // relativ zu storage/uploads/
        public readonly int $position,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
