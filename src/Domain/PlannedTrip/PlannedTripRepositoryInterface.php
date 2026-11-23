<?php

declare(strict_types=1);

namespace App\Domain\PlannedTrip;

interface PlannedTripRepositoryInterface
{
    /** @return PlannedTrip[] by date, then sort order */
    public function findByRental(string $rentalId): array;

    public function findById(string $id): ?PlannedTrip;

    public function save(PlannedTrip $trip): void;

    public function delete(string $id): void;
}
