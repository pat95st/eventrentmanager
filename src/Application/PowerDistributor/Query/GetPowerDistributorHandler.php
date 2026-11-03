<?php

declare(strict_types=1);

namespace App\Application\PowerDistributor\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\PowerDistributor\PowerDistributor;
use App\Domain\PowerDistributor\PowerDistributorRepositoryInterface;

final class GetPowerDistributorHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PowerDistributorRepositoryInterface $distributors)
    {
    }

    public function handle(QueryInterface $query): ?PowerDistributor
    {
        assert($query instanceof GetPowerDistributor);

        return $this->distributors->findByArticle($query->articleId);
    }
}
