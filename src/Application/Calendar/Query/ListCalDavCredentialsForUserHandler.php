<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Calendar\CalDavCredentialRepositoryInterface;

final class ListCalDavCredentialsForUserHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CalDavCredentialRepositoryInterface $repository)
    {
    }

    /** @return \App\Domain\Calendar\CalDavCredential[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListCalDavCredentialsForUser);

        return $this->repository->findByUser($query->userId);
    }
}
