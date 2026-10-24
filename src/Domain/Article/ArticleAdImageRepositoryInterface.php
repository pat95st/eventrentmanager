<?php

declare(strict_types=1);

namespace App\Domain\Article;

interface ArticleAdImageRepositoryInterface
{
    public function save(ArticleAdImage $image): void;

    public function findById(string $id): ?ArticleAdImage;

    /** @return ArticleAdImage[] neueste zuerst */
    public function findByArticle(string $articleId): array;

    public function delete(string $id): void;
}
