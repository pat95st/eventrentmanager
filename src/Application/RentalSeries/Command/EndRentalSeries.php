<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandInterface;

/**
 * Ends a series. Future occurrences (start after today) are either deleted
 * (only those whose material has not been issued yet) or kept as ordinary
 * rentals.
 */
final class EndRentalSeries implements CommandInterface
{
    public function __construct(
        public readonly string $seriesId,
        public readonly bool $deleteFutureOccurrences,
    ) {
    }
}
