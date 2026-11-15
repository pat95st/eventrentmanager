<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

use App\Domain\PowerDistributor\ConnectorType;
use App\Domain\PowerDistributor\Outlet;
use App\Domain\Venue\PowerConnection;

/**
 * Suggests re-plugging units so that no phase of a venue connection or
 * distributor inlet, no integrated protective device and no outlet is
 * overloaded (migration 157).
 *
 * Greedy search: per step, every unit that contributes to an overloaded
 * element is tried on every free outlet with the same connector type (the
 * plug has to fit) – including other phases where the phase can be chosen.
 * The move that reduces the total overload (sum of all overloads in ampere)
 * the most is taken; ties are broken by the lower maximum utilisation.
 * Stops when nothing is overloaded, nothing improves or after MAX_MOVES.
 */
final class PowerRebalancer
{
    private const MAX_MOVES = 12;
    private const MAX_CANDIDATES = 30;
    private const PHASES = ['L1', 'L2', 'L3'];

    public function __construct(private readonly PowerLoadCalculator $calculator = new PowerLoadCalculator())
    {
    }

    /**
     * @param PowerNode[] $nodes
     * @param PowerConnection[] $venueConnections
     * @param array<string, PowerAssignment> $assignments
     * @return array{moves: PowerMoveSuggestion[], result: PowerLoadResult} moves and the result after applying them
     */
    public function suggest(array $nodes, array $venueConnections, array $assignments, float $defaultFactor): array
    {
        $byKey = [];
        foreach ($nodes as $node) {
            $byKey[$node->key()] = $node;
        }
        $original = $assignments;
        $result = $this->calculator->calculate($nodes, $venueConnections, $assignments, $defaultFactor);
        $movedKeys = [];

        for ($step = 0; $step < self::MAX_MOVES && $result->hasOverloads(); $step++) {
            $score = $result->overloadScore();
            $best = null;
            foreach ($this->candidates($byKey, $result, $defaultFactor) as $key) {
                $current = $result->validAssignments[$key];
                foreach ($this->targets($key, $current, $byKey, $result) as $target) {
                    $trial = $assignments;
                    $trial[$key] = $target;
                    $trialResult = $this->calculator->calculate($nodes, $venueConnections, $trial, $defaultFactor);
                    $trialScore = $trialResult->overloadScore();
                    if ($trialScore >= $score - 0.01) {
                        continue;
                    }
                    if (
                        $best === null
                        || $trialScore < $best['score'] - 1e-6
                        || (abs($trialScore - $best['score']) <= 1e-6 && $trialResult->maxUtilization() < $best['result']->maxUtilization())
                    ) {
                        $best = ['key' => $key, 'assignment' => $target, 'score' => $trialScore, 'result' => $trialResult];
                    }
                }
            }
            if ($best === null) {
                break;
            }
            $assignments[$best['key']] = $best['assignment'];
            $movedKeys[$best['key']] = true;
            $result = $best['result'];
        }

        $moves = [];
        foreach (array_keys($movedKeys) as $key) {
            $from = $original[$key];
            $to = $assignments[$key];
            if ($from->targetKey() === $to->targetKey() && $from->outletId === $to->outletId && $from->phase === $to->phase) {
                continue; // moved back and forth
            }
            $moves[] = new PowerMoveSuggestion($key, $byKey[$key]->label, $from, $to);
        }

        return ['moves' => $moves, 'result' => $result];
    }

    /**
     * Units plugged into an outlet that contribute to an overloaded element, biggest load first.
     *
     * @param array<string, PowerNode> $nodes
     * @return string[]
     */
    private function candidates(array $nodes, PowerLoadResult $result, float $defaultFactor): array
    {
        $overloadedNodes = [];
        $overloadedVenues = [];
        foreach ($result->overloads() as $check) {
            if ($check->scope === LoadCheck::SCOPE_VENUE) {
                $overloadedVenues[(string) $check->elementId] = true;
            } elseif ($check->nodeKey !== null) {
                $overloadedNodes[$check->nodeKey] = true;
            }
        }

        $weights = [];
        foreach ($result->validAssignments as $key => $assignment) {
            if (!$assignment->isOutlet()) {
                continue;
            }
            $affected = false;
            $current = $assignment;
            $guard = 0;
            while ($current !== null && $guard++ < 50) {
                $target = $current->targetKey();
                if ($target === null) {
                    $affected = $affected || isset($overloadedVenues[(string) $current->venueConnectionId]);
                    break;
                }
                if (isset($overloadedNodes[$target])) {
                    $affected = true;
                    break;
                }
                $current = $result->validAssignments[$target] ?? null;
            }
            if ($affected) {
                $weights[$key] = $this->weight($key, $nodes, $result, $defaultFactor);
            }
        }
        arsort($weights);

        return array_slice(array_keys(array_filter($weights, static fn (float $w) => $w > 0)), 0, self::MAX_CANDIDATES);
    }

    /** @param array<string, PowerNode> $nodes */
    private function weight(string $key, array $nodes, PowerLoadResult $result, float $defaultFactor): float
    {
        $node = $nodes[$key];
        if ($node->distributor === null) {
            return max(0, $node->inputPowerW ?? 0) * ($result->validAssignments[$key]->simultaneityFactor ?? $defaultFactor);
        }

        return array_sum($result->distributorLoads[$key] ?? []);
    }

    /**
     * Alternative assignments of a unit: free outlets with the same connector type.
     *
     * @param array<string, PowerNode> $nodes
     * @return PowerAssignment[]
     */
    private function targets(string $key, PowerAssignment $current, array $nodes, PowerLoadResult $result): array
    {
        $currentTarget = $nodes[(string) $current->targetKey()] ?? null;
        $currentOutlet = $currentTarget?->distributor !== null ? $this->outlet($currentTarget->distributor->outlets, (string) $current->outletId) : null;
        if ($currentOutlet === null) {
            return [];
        }
        $plug = $currentOutlet->connectorType;
        $excluded = $this->descendants($key, $result) + [$key => true];
        $node = $nodes[$key];

        $targets = [];
        foreach ($nodes as $targetKey => $target) {
            if ($target->distributor === null || isset($excluded[$targetKey])) {
                continue;
            }
            foreach ($target->distributor->outlets as $outlet) {
                if ($outlet->connectorType !== $plug) {
                    continue;
                }
                $occupants = array_diff($result->outletChildren[$targetKey][$outlet->id] ?? [], [$key]);
                if (count($occupants) >= $outlet->quantity) {
                    continue;
                }
                foreach ($this->phaseOptions($node, $current, $target, $outlet, $plug) as $phase) {
                    if ($targetKey === $current->targetKey() && $outlet->id === $current->outletId && $phase === $current->phase) {
                        continue;
                    }
                    $targets[] = $current->movedTo($target->articleId, $target->unitNo, $outlet->id, $phase);
                }
            }
        }

        return $targets;
    }

    /** @return array<int, string|null> phases worth trying on the outlet (null = follows from the outlet) */
    private function phaseOptions(PowerNode $node, PowerAssignment $current, PowerNode $target, Outlet $outlet, ConnectorType $plug): array
    {
        $distributor = $target->distributor;
        assert($distributor !== null);
        if (!$distributor->inletType->isThreePhase()) {
            return [null];
        }
        if ($plug->isThreePhase()) {
            $singlePhaseLoad = $node->distributor !== null
                ? !$node->distributor->inletType->isThreePhase()
                : $current->phase !== null;

            return $singlePhaseLoad ? self::PHASES : [null];
        }

        return $distributor->phaseOf($outlet) !== null ? [null] : self::PHASES;
    }

    /** @return array<string, true> units plugged (directly or indirectly) into the given distributor unit */
    private function descendants(string $key, PowerLoadResult $result): array
    {
        $found = [];
        $queue = [$key];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($result->outletChildren[$current] ?? [] as $children) {
                foreach ($children as $child) {
                    if (!isset($found[$child])) {
                        $found[$child] = true;
                        $queue[] = $child;
                    }
                }
            }
        }

        return $found;
    }

    /** @param Outlet[] $outlets */
    private function outlet(array $outlets, string $id): ?Outlet
    {
        foreach ($outlets as $outlet) {
            if ($outlet->id === $id) {
                return $outlet;
            }
        }

        return null;
    }
}
