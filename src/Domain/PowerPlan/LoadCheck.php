<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

/**
 * Current through one limited element of the power plan: a venue power
 * connection, the inlet of a distributor unit, an integrated protective
 * device (MCB/RCD/RCBO/fuse) or an outlet. $phase is null for single-phase
 * elements whose phase is irrelevant for the check.
 */
final class LoadCheck
{
    public const SCOPE_VENUE = 'venue';
    public const SCOPE_INLET = 'inlet';
    public const SCOPE_DEVICE = 'device';
    public const SCOPE_OUTLET = 'outlet';

    public function __construct(
        public readonly string $scope,
        public readonly string $label,
        public readonly ?string $phase,
        public readonly float $currentA,
        public readonly ?int $limitA,
        public readonly ?string $nodeKey = null,
        public readonly ?string $elementId = null,
    ) {
    }

    public function isOverloaded(): bool
    {
        return $this->limitA !== null && $this->currentA > $this->limitA + 1e-6;
    }

    public function overloadA(): float
    {
        return $this->limitA !== null ? max(0.0, $this->currentA - $this->limitA) : 0.0;
    }

    /** Utilisation in percent (null without limit). */
    public function utilization(): ?float
    {
        return $this->limitA !== null && $this->limitA > 0 ? $this->currentA / $this->limitA * 100 : null;
    }
}
