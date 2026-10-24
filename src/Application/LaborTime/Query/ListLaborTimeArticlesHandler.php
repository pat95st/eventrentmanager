<?php

declare(strict_types=1);

namespace App\Application\LaborTime\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;

final class ListLaborTimeArticlesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles)
    {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListLaborTimeArticles);

        return $this->laborTimeArticles->findAll();
    }
}
