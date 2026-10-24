<?php

declare(strict_types=1);

namespace App\Domain\Article;

interface ArticleChannelListingRepositoryInterface
{
    /** @return ArticleChannelListing[] indiziert nach Channel-Value, z. B. 'google_merchant' */
    public function findByArticle(string $articleId): array;

    public function findByArticleAndChannel(string $articleId, SalesChannel $channel): ?ArticleChannelListing;

    /** Legt neu an oder aktualisiert (UPSERT über das UNIQUE KEY article_id+channel). */
    public function save(ArticleChannelListing $listing): void;
}
