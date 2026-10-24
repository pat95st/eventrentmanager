<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

final class AddVenuePowerConnection implements CommandInterface
{
    public function __construct(
        public readonly string $venueId,
        public readonly string $type,
        public readonly string $breakerRating,
        public readonly string $rcdTripCurrent,
        public readonly ?string $location,
    ) {
    }
}
