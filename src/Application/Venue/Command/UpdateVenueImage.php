<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

/** Change the description of a venue photo. */
final class UpdateVenueImage implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $venueId,
        public readonly ?string $description,
    ) {
    }
}
