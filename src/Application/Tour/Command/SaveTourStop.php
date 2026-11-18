<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Creates ($stopId null) or updates a stop; date values as 'Y-m-d\TH:i'. */
final class SaveTourStop implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly ?string $stopId,
        public readonly string $location,
        public readonly string $setupAt,
        public readonly string $eventStartAt,
        public readonly string $eventEndAt,
        public readonly string $teardownEndAt,
        public readonly string $contactName,
        public readonly string $contactPhone,
        public readonly string $note,
    ) {
    }
}
