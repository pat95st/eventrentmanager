<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Venue\PowerConnection;
use App\Domain\Venue\VenuePowerConnectionRepositoryInterface;

final class InMemoryVenuePowerConnectionRepository implements VenuePowerConnectionRepositoryInterface
{
    /** @var array<string, PowerConnection> */
    private array $connections = [];

    public function save(PowerConnection $connection): void
    {
        $this->connections[$connection->id] = $connection;
    }

    public function findById(string $id): ?PowerConnection
    {
        return $this->connections[$id] ?? null;
    }

    public function findByVenue(string $venueId): array
    {
        return array_values(array_filter(
            $this->connections,
            static fn (PowerConnection $connection) => $connection->venueId === $venueId
        ));
    }

    public function findByVenues(array $venueIds): array
    {
        $grouped = [];
        foreach ($venueIds as $venueId) {
            $grouped[$venueId] = $this->findByVenue($venueId);
        }

        return $grouped;
    }

    public function delete(string $id): void
    {
        unset($this->connections[$id]);
    }
}
