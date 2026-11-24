<?php

declare(strict_types=1);

namespace App\Application\PlannedTrip\Command;

use App\Application\Command\CommandInterface;

/** Adds (tripId null) or changes a planned trip of an event (migration 186). */
final class SavePlannedTrip implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly ?string $tripId,
        public readonly ?string $tripDate,
        public readonly ?string $from,
        public readonly ?string $fromText,
        public readonly ?string $to,
        public readonly ?string $toText,
        public readonly ?string $kmOverride,
    ) {
    }
}
