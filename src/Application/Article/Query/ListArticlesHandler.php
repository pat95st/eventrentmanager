<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;

final class ListArticlesHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListArticles);

        $categoryIds = [];
        if ($query->categoryId !== null) {
            $categoryIds = array_merge([$query->categoryId], $this->categories->findDescendantIds($query->categoryId));
        }

        $totalCount = $this->articles->countSearch($query->searchTerm, $query->manufacturerId, $categoryIds, $query->onlyActive);

        if ($query->pageSize === null) {
            // Kein Limit angefordert (Dropdown-Nutzung): alle Treffer laden.
            $items = $this->articles->search($query->searchTerm, $query->manufacturerId, $categoryIds, max($totalCount, 1), 0, $query->onlyActive);

            return new PaginatedResult($items, $totalCount, 1, max($totalCount, 1));
        }

        $offset = PaginatedResult::offsetFor($query->page ?? 1, $query->pageSize);
        $items = $this->articles->search($query->searchTerm, $query->manufacturerId, $categoryIds, $query->pageSize, $offset, $query->onlyActive);

        return new PaginatedResult($items, $totalCount, $query->page ?? 1, $query->pageSize);
    }
}
