<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\PlannedTrip\PlannedTrip;
use App\Domain\PlannedTrip\PlannedTripRepositoryInterface;

/** In-memory test double of PlannedTripRepositoryInterface. */
final class InMemoryPlannedTripRepository implements PlannedTripRepositoryInterface
{
    /** @var array<string, PlannedTrip> */
    public array $trips = [];

    public function findByRental(string $rentalId): array
    {
        $trips = array_values(array_map(static fn (PlannedTrip $t) => clone $t, array_filter($this->trips, static fn (PlannedTrip $t) => $t->rentalId === $rentalId)));
        usort($trips, static fn (PlannedTrip $a, PlannedTrip $b) => [$a->tripDate, $a->sortOrder] <=> [$b->tripDate, $b->sortOrder]);

        return $trips;
    }

    public function findById(string $id): ?PlannedTrip
    {
        return isset($this->trips[$id]) ? clone $this->trips[$id] : null;
    }

    public function save(PlannedTrip $trip): void
    {
        $this->trips[$trip->id] = clone $trip;
    }

    public function delete(string $id): void
    {
        unset($this->trips[$id]);
    }
}
