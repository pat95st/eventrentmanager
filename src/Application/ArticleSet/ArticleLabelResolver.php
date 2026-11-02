<?php

declare(strict_types=1);

namespace App\Application\ArticleSet;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

/** "Hersteller Artikelname" je Artikel-ID, mit Cache (Sets, Upgrades). */
final class ArticleLabelResolver
{
    /** @var array<string, Article|null> */
    private array $articles = [];
    /** @var array<string, \App\Domain\Manufacturer\Manufacturer|null> */
    private array $manufacturers = [];

    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ManufacturerRepositoryInterface $manufacturerRepository,
    ) {
    }

    public function article(string $articleId): ?Article
    {
        if (!array_key_exists($articleId, $this->articles)) {
            $this->articles[$articleId] = $this->articleRepository->findById($articleId);
        }

        return $this->articles[$articleId];
    }

    public function label(string $articleId): string
    {
        $article = $this->article($articleId);
        if ($article === null) {
            return '(gelöschter Artikel)';
        }
        $manufacturerId = $article->manufacturerId;
        if ($manufacturerId !== null && !array_key_exists($manufacturerId, $this->manufacturers)) {
            $this->manufacturers[$manufacturerId] = $this->manufacturerRepository->findById($manufacturerId);
        }

        return ArticleDisplayName::of($article, $manufacturerId !== null ? $this->manufacturers[$manufacturerId] : null);
    }
}
