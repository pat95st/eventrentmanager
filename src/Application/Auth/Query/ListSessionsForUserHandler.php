<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Auth\UserSessionRepositoryInterface;

final class ListSessionsForUserHandler implements QueryHandlerInterface
{
    public function __construct(private readonly UserSessionRepositoryInterface $sessions)
    {
    }

    /** @return SessionListEntry[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListSessionsForUser);

        return array_map(
            fn ($session) => new SessionListEntry($session, $session->id === $query->currentSessionId),
            $this->sessions->findByUser($query->userId),
        );
    }
}
