<?php

declare(strict_types=1);

namespace App\Domain\Venue;

interface VenueStageRepositoryInterface
{
    /** Speichert die Bühne samt ihrer Entfernungen zu den Stromanschlüssen (ersetzt die bisherigen). */
    public function save(Stage $stage): void;

    public function findById(string $id): ?Stage;

    /** @return Stage[] */
    public function findByVenue(string $venueId): array;

    public function delete(string $id): void;
}
