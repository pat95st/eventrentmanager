<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\Query\QueryInterface;

/** Proposed occurrences for a new series created from an existing rental. */
final class PreviewRentalSeries implements QueryInterface
{
    /** @param array<string, mixed> $ruleInput see RecurrenceRule::fromInput() */
    public function __construct(
        public readonly string $sourceRentalId,
        public readonly array $ruleInput,
        public readonly string $nameTemplate = '',
    ) {
    }
}
