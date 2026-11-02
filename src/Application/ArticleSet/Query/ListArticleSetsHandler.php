<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;

final class ListArticleSetsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ArticleSetRepositoryInterface $sets,
        private readonly GetArticleSetHandler $getHandler,
    ) {
    }

    /** @return ArticleSetDetail[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListArticleSets);

        return array_map(
            fn ($set) => $this->getHandler->handle(new GetArticleSet($set->id)),
            $this->sets->findAll($query->onlyActive),
        );
    }
}
