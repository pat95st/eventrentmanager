<?php

declare(strict_types=1);

namespace App\Domain\Venue;

interface VenueImageRepositoryInterface
{
    /** @return VenueImage[] sorted by position */
    public function findByVenue(string $venueId): array;

    public function findById(string $id): ?VenueImage;

    public function save(VenueImage $image): void;

    public function delete(string $id): void;
}
