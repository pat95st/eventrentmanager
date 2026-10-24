<?php

declare(strict_types=1);

namespace App\Application\Category\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Category\Category;
use App\Domain\Category\CategoryRepositoryInterface;

final class ListCategoriesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CategoryRepositoryInterface $categories)
    {
    }

    /** @return Category[] Alle Kategorien flach, alphabetisch — der Baum wird in der View aus parentId gebaut. */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListCategories);

        return $this->categories->findAll();
    }
}
