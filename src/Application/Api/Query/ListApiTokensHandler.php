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

        // Tokens issued via OAuth rotate hourly and are shown per connection instead.
        return array_values(array_filter($this->repository->findAll(), static fn ($token) => !$token->isOAuth()));
    }
}
