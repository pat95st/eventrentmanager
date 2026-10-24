<?php

declare(strict_types=1);

namespace App\Domain\Article;

interface ArticlePreIssueCheckRepositoryInterface
{
    public function save(ArticlePreIssueCheck $check): void;

    public function findById(string $id): ?ArticlePreIssueCheck;

    /** @return ArticlePreIssueCheck[] */
    public function findByArticle(string $articleId): array;

    public function delete(string $id): void;
}
