<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandInterface;

/**
 * New rhythm from $firstDate on. Occurrences from that date that are
 * neither started nor moved by hand are moved to the new dates (keeping
 * their data); missing dates are created from the previous occurrence,
 * surplus occurrences are deleted.
 */
final class ChangeRentalSeriesRule implements CommandInterface
{
    /** @param array<string, mixed> $ruleInput see RecurrenceRule::fromInput() */
    public function __construct(
        public readonly string $seriesId,
        public readonly string $firstDate,
        public readonly array $ruleInput,
    ) {
    }
}
