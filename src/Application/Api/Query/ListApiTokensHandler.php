<?php

declare(strict_types=1);

namespace App\Application\Api\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Api\ApiTokenRepositoryInterface;

final class ListApiTokensHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ApiTokenRepositoryInterface $repository)
    {
    }

    /** @return \App\Domain\Api\ApiToken[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListApiTokens);

        return $this->repository->findAll();
    }
}
