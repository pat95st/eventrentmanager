<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Venue\Stage;
use App\Domain\Venue\VenueStageRepositoryInterface;

final class InMemoryVenueStageRepository implements VenueStageRepositoryInterface
{
    /** @var array<string, Stage> */
    private array $stages = [];

    public function save(Stage $stage): void
    {
        $this->stages[$stage->id] = $stage;
    }

    public function findById(string $id): ?Stage
    {
        return $this->stages[$id] ?? null;
    }

    public function findByVenue(string $venueId): array
    {
        return array_values(array_filter($this->stages, static fn (Stage $stage) => $stage->venueId === $venueId));
    }

    public function delete(string $id): void
    {
        unset($this->stages[$id]);
    }
}
