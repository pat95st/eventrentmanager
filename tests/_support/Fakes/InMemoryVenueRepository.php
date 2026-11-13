<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Venue\Venue;
use App\Domain\Venue\VenueRepositoryInterface;

final class InMemoryVenueRepository implements VenueRepositoryInterface
{
    /** @var array<string, Venue> */
    private array $venues = [];

    public function save(Venue $venue): void
    {
        $this->venues[$venue->id] = $venue;
    }

    public function findById(string $id): ?Venue
    {
        return $this->venues[$id] ?? null;
    }

    public function findByName(string $name): ?Venue
    {
        foreach ($this->venues as $venue) {
            if (mb_strtolower($venue->name) === mb_strtolower($name)) {
                return $venue;
            }
        }

        return null;
    }

    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
    {
        $venues = array_values(array_filter(
            $this->venues,
            static fn (Venue $venue) => $search === null || $search === '' || mb_stripos($venue->name, $search) !== false
        ));
        usort($venues, static fn (Venue $a, Venue $b) => strcasecmp($a->name, $b->name));

        return array_slice($venues, $offset ?? 0, $limit);
    }

    public function countAll(?string $search = null): int
    {
        return count($this->findAll($search));
    }

    public function isUsedByRentals(string $id): bool
    {
        return false;
    }

    public function delete(string $id): void
    {
        unset($this->venues[$id]);
    }
}
