<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandInterface;

/**
 * Makes an existing rental the first occurrence of a new series and creates
 * the occurrences confirmed in the preview table.
 */
final class CreateRentalSeries implements CommandInterface
{
    /**
     * @param array<string, mixed> $ruleInput see RecurrenceRule::fromInput()
     * @param string[] $scopes CopyScope values
     * @param list<array{start: string, end: string, location: string, include: bool, default_start: string, default_end: string}> $rows
     */
    public function __construct(
        public readonly string $sourceRentalId,
        public readonly array $ruleInput,
        public readonly string $nameTemplate,
        public readonly array $scopes,
        public readonly array $rows,
    ) {
    }
}
