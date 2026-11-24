<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\OAuth\OAuthClient;
use App\Domain\OAuth\OAuthClientRepositoryInterface;
use DateTimeImmutable;

/** In-memory test double for OAuthClientRepositoryInterface. */
final class InMemoryOAuthClientRepository implements OAuthClientRepositoryInterface
{
    /** @var array<string, OAuthClient> */
    public array $clients = [];

    public function findById(string $id): ?OAuthClient
    {
        return $this->clients[$id] ?? null;
    }

    public function save(OAuthClient $client): void
    {
        $this->clients[$client->id] = $client;
    }

    public function touchLastUsed(string $id, DateTimeImmutable $at): void
    {
        if (isset($this->clients[$id])) {
            $this->clients[$id]->lastUsedAt = $at;
        }
    }

    public function purgeUnusedOlderThan(DateTimeImmutable $before): void
    {
        // Grants are not known here; nothing to purge in tests.
    }
}
