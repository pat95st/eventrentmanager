<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Command;

use App\Application\Command\CommandInterface;

/**
 * Saves the simultaneity factor and where each unit is plugged in
 * (migration 159). $rows: unit key ("<article>#<unit>") => target (see
 * PowerTargetOptions), phase and optional factor of the unit.
 */
final class SaveRentalPowerPlan implements CommandInterface
{
    /** @param array<string, array{target?: string, phase?: string, factor?: string}> $rows */
    public function __construct(
        public readonly string $rentalId,
        public readonly ?string $simultaneityFactor,
        public readonly array $rows,
    ) {
    }
}
