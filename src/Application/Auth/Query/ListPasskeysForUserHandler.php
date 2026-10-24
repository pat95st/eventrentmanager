<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Auth\WebauthnCredential;
use App\Domain\Auth\WebauthnCredentialRepositoryInterface;

final class ListPasskeysForUserHandler implements QueryHandlerInterface
{
    public function __construct(private readonly WebauthnCredentialRepositoryInterface $credentials)
    {
    }

    /** @return WebauthnCredential[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListPasskeysForUser);

        return $this->credentials->findAllForUser($query->userId);
    }
}
