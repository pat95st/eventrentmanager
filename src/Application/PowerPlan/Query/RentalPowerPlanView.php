<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Query;

use App\Application\PowerPlan\PowerTargetOptions;
use App\Application\PowerPlan\RentalPowerSetup;
use App\Domain\PowerPlan\PowerLoadResult;
use App\Domain\PowerPlan\PowerMoveSuggestion;

final class RentalPowerPlanView
{
    /** @param PowerMoveSuggestion[] $suggestions */
    public function __construct(
        public readonly RentalPowerSetup $setup,
        public readonly PowerLoadResult $result,
        public readonly array $suggestions,
        public readonly PowerLoadResult $resultAfterSuggestions,
        public readonly PowerTargetOptions $targets,
    ) {
    }
}
