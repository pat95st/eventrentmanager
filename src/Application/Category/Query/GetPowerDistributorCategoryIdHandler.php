<?php

declare(strict_types=1);

namespace App\Application\Category\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Category\CategoryRepositoryInterface;

final class GetPowerDistributorCategoryIdHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CategoryRepositoryInterface $categories)
    {
    }

    public function handle(QueryInterface $query): ?string
    {
        assert($query instanceof GetPowerDistributorCategoryId);

        return $this->categories->findPowerDistributorCategoryId();
    }
}
