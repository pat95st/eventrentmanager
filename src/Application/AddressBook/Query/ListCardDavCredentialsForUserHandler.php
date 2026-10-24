<?php

declare(strict_types=1);

namespace App\Application\AddressBook\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\AddressBook\CardDavCredentialRepositoryInterface;

final class ListCardDavCredentialsForUserHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CardDavCredentialRepositoryInterface $repository)
    {
    }

    /** @return \App\Domain\AddressBook\CardDavCredential[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListCardDavCredentialsForUser);

        return $this->repository->findByUser($query->userId);
    }
}
