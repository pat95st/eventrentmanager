<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

/** Result of PowerLoadCalculator: loads per phase (W, index 0–2 = L1–L3) and checked limits. */
final class PowerLoadResult
{
    /**
     * @param LoadCheck[] $checks
     * @param array<string, float[]> $distributorLoads distributor unit key => load per phase in its internal phases
     * @param array<string, float[]> $venueLoads venue connection ID => load per phase
     * @param string[] $unassignedKeys consumer units with input power that are not plugged in anywhere
     * @param string[] $warnings
     * @param array<string, array<string, string[]>> $outletChildren distributor key => outlet ID => unit keys
     * @param array<string, string[]> $venueChildren venue connection ID => unit keys
     * @param array<string, PowerAssignment> $validAssignments
     */
    public function __construct(
        public readonly array $checks,
        public readonly array $distributorLoads,
        public readonly array $venueLoads,
        public readonly array $unassignedKeys,
        public readonly array $warnings,
        public readonly float $totalEffectivePowerW,
        public readonly array $outletChildren = [],
        public readonly array $venueChildren = [],
        public readonly array $validAssignments = [],
    ) {
    }

    /** @return LoadCheck[] */
    public function overloads(): array
    {
        return array_values(array_filter($this->checks, static fn (LoadCheck $c) => $c->isOverloaded()));
    }

    public function hasOverloads(): bool
    {
        return $this->overloads() !== [];
    }

    /** Sum of all overloads in ampere (0 = no overload) – the target of the rebalancing. */
    public function overloadScore(): float
    {
        return array_sum(array_map(static fn (LoadCheck $c) => $c->overloadA(), $this->checks));
    }

    /** Highest utilisation of all limited elements in percent. */
    public function maxUtilization(): float
    {
        $max = 0.0;
        foreach ($this->checks as $check) {
            $max = max($max, $check->utilization() ?? 0.0);
        }

        return $max;
    }

    /** @return LoadCheck[] checks of one distributor unit (or venue connection), in calculation order */
    public function checksFor(string $nodeKeyOrConnectionId): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (LoadCheck $c) => $c->nodeKey === $nodeKeyOrConnectionId || ($c->scope === LoadCheck::SCOPE_VENUE && $c->elementId === $nodeKeyOrConnectionId),
        ));
    }
}
