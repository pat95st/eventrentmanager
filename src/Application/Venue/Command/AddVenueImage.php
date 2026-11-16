<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

/** Upload a photo of a venue with an optional description (migration 169). */
final class AddVenueImage implements CommandInterface
{
    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $uploadedFile */
    public function __construct(
        public readonly string $venueId,
        public readonly array $uploadedFile,
        public readonly ?string $description = null,
    ) {
    }
}
