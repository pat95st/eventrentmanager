<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Command;

use App\Application\Command\CommandInterface;

/**
 * Applies re-plugging suggestions (migration 157). The suggestions are
 * computed again from the saved plan; $keys limits them to these units
 * (null = all suggestions).
 */
final class ApplyRentalPowerSuggestions implements CommandInterface
{
    /** @param string[]|null $keys */
    public function __construct(
        public readonly string $rentalId,
        public readonly ?array $keys = null,
    ) {
    }
}
