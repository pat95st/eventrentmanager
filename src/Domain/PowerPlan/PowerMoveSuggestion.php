<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

/** Suggested re-plugging of one unit (see PowerRebalancer). */
final class PowerMoveSuggestion
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly PowerAssignment $from,
        public readonly PowerAssignment $to,
    ) {
    }
}
