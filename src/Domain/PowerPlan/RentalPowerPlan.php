<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

/** Power plan of an event (migration 159): simultaneity factor and plug assignments. */
final class RentalPowerPlan
{
    public const DEFAULT_FACTOR = 1.0;
    public const MIN_FACTOR = 0.01;
    public const MAX_FACTOR = 1.0;

    /** @param array<string, PowerAssignment> $assignments keyed by PowerNode::keyOf() */
    public function __construct(
        public readonly string $rentalId,
        public float $simultaneityFactor = self::DEFAULT_FACTOR,
        public array $assignments = [],
    ) {
    }

    public function assignment(string $key): ?PowerAssignment
    {
        return $this->assignments[$key] ?? null;
    }
}
