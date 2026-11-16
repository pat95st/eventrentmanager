<?php

declare(strict_types=1);

namespace App\Domain\Venue;

use DateTimeImmutable;

/**
 * Photo of a venue (migration 167) with an optional description, e.g. the
 * access route, the loading dock or the power distribution.
 */
final class VenueImage
{
    public const MAX_DESCRIPTION_LENGTH = 2000;

    public function __construct(
        public readonly string $id,
        public readonly string $venueId,
        public readonly string $filePath, // relative to storage/uploads/venues, e.g. "<venue-id>/<image-id>.jpg"
        public ?string $description,
        public int $position,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
