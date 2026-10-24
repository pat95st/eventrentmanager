<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\Article;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

/**
 * Delegiert die eigentliche Suche/Paginierung an ListArticlesHandler und
 * reichert die Treffer anschließend um Hersteller/Kategorie an (siehe
 * ArticleListItem). Das ist bewusst ein separater Use Case statt einer
 * Änderung von ListArticles selbst, weil mehrere andere Konsumenten
 * (Artikel-Dropdowns in Angeboten/Rechnungen/Vermietungen/Case-Inhalten,
 * Preisliste) nur die reinen Article-Objekte benötigen und nicht mit der
 * zusätzlichen Auflösung belastet werden sollen.
 */
final class ListArticlesForDisplayHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ListArticlesHandler $listArticles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListArticlesForDisplay);

        $result = $this->listArticles->handle(new ListArticles(
            $query->searchTerm,
            $query->manufacturerId,
            $query->categoryId,
            $query->page,
            $query->pageSize,
            $query->onlyActive,
        ));

        $manufacturerCache = [];
        $categoryCache = [];

        $items = array_map(
            function (Article $article) use (&$manufacturerCache, &$categoryCache): ArticleListItem {
                if (!array_key_exists($article->manufacturerId, $manufacturerCache)) {
                    $manufacturerCache[$article->manufacturerId] = $this->manufacturers->findById($article->manufacturerId);
                }

                $category = null;
                if ($article->categoryId !== null) {
                    if (!array_key_exists($article->categoryId, $categoryCache)) {
                        $categoryCache[$article->categoryId] = $this->categories->findById($article->categoryId);
                    }
                    $category = $categoryCache[$article->categoryId];
                }

                return new ArticleListItem($article, $manufacturerCache[$article->manufacturerId], $category);
            },
            $result->items
        );

        return new PaginatedResult($items, $result->totalCount, $result->page, $result->pageSize);
    }
}
