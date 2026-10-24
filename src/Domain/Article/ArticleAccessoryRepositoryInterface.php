<?php

declare(strict_types=1);

namespace App\Domain\Article;

interface ArticleAccessoryRepositoryInterface
{
    public function save(ArticleAccessory $accessory): void;

    public function findById(string $id): ?ArticleAccessory;

    /** @return ArticleAccessory[] */
    public function findByArticle(string $articleId): array;

    public function delete(string $id): void;
}
