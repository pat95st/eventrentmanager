<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Display\DisplayClient;
use App\Domain\Display\DisplayClientRepositoryInterface;
use DateTimeImmutable;

final class InMemoryDisplayClientRepository implements DisplayClientRepositoryInterface
{
    /** @var array<string, DisplayClient> */
    private array $clients = [];

    public function findAll(): array
    {
        return array_map(static fn (DisplayClient $c): DisplayClient => clone $c, array_values($this->clients));
    }

    public function findById(string $id): ?DisplayClient
    {
        return isset($this->clients[$id]) ? clone $this->clients[$id] : null;
    }

    public function findByTokenHash(string $hash): ?DisplayClient
    {
        foreach ($this->clients as $client) {
            if ($client->tokenHash === $hash) {
                return clone $client;
            }
        }

        return null;
    }

    public function save(DisplayClient $client): void
    {
        $this->clients[$client->id] = clone $client;
    }

    public function delete(string $id): void
    {
        unset($this->clients[$id]);
    }

    public function touch(string $id, DateTimeImmutable $at, ?string $ip, ?string $version): void
    {
        if (isset($this->clients[$id])) {
            $this->clients[$id]->lastSeenAt = $at;
            $this->clients[$id]->lastIp = $ip;
            $this->clients[$id]->clientVersion = $version ?? $this->clients[$id]->clientVersion;
        }
    }
}
