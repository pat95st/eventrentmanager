<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandInterface;

/**
 * Generates further occurrences after the last one. Each new occurrence
 * takes its data over from the occurrence before it.
 */
final class ExtendRentalSeries implements CommandInterface
{
    /** @param string[] $scopes CopyScope values */
    public function __construct(
        public readonly string $seriesId,
        public readonly string $endMode,    // 'date' | 'count' (ignored for manual series)
        public readonly string $endsOn,
        public readonly string $count,
        public readonly string $manualDates, // manual series only
        public readonly array $scopes,
    ) {
    }
}
