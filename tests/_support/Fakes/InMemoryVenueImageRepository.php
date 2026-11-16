<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Venue\VenueImage;
use App\Domain\Venue\VenueImageRepositoryInterface;

final class InMemoryVenueImageRepository implements VenueImageRepositoryInterface
{
    /** @var array<string, VenueImage> */
    private array $images = [];

    public function findByVenue(string $venueId): array
    {
        $images = array_values(array_filter($this->images, static fn (VenueImage $image) => $image->venueId === $venueId));
        usort($images, static fn (VenueImage $a, VenueImage $b) => $a->position <=> $b->position);

        return $images;
    }

    public function findById(string $id): ?VenueImage
    {
        return $this->images[$id] ?? null;
    }

    public function save(VenueImage $image): void
    {
        $this->images[$image->id] = $image;
    }

    public function delete(string $id): void
    {
        unset($this->images[$id]);
    }
}
