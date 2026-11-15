<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

use App\Domain\PowerDistributor\Outlet;
use App\Domain\PowerDistributor\PowerDistributor;
use App\Domain\Venue\PowerConnection;

/**
 * Computes the expected load per phase of an event's power plan
 * (migration 157).
 *
 * Model: 230 V per phase, cos φ = 1, so I = P / 230 V. The effective power
 * of a consumer is its input power times the simultaneity factor (of the
 * unit, else of the event). A consumer on a three-phase supply (CEE red
 * outlet or connection) without a chosen phase is treated as a symmetrical
 * three-phase load; on a single-phase supply its whole load is on that
 * phase. A distributor with a three-phase inlet passes its phases through
 * 1:1; a distributor with a single-phase inlet puts its whole load on the
 * phase it is supplied from.
 *
 * Checked limits: venue connection (breaker rating), distributor inlet
 * (connector rating), integrated protective devices (rated current) and
 * outlets (connector rating).
 */
final class PowerLoadCalculator
{
    public const VOLTAGE = 230.0;
    private const PHASES = ['L1', 'L2', 'L3'];

    /** @var array<string, PowerNode> */
    private array $nodes = [];
    /** @var array<string, PowerConnection> */
    private array $venueConnections = [];
    /** @var array<string, PowerAssignment> valid assignments only */
    private array $assignments = [];
    private float $defaultFactor = 1.0;
    /** @var array<string, float> unit key => own simultaneity factor (also for units not plugged in) */
    private array $factors = [];

    /** @var array<string, array<string, string[]>> distributor key => outlet ID => child keys */
    private array $outletChildren = [];
    /** @var array<string, string[]> venue connection ID => child keys */
    private array $venueChildren = [];
    /** @var array<string, array{vector: float[], outlets: array<string, float[]>, placed: array<string, float[]>}> */
    private array $memo = [];
    /** @var array<string, true> */
    private array $visiting = [];
    /** @var string[] */
    private array $warnings = [];
    /** @var array<string, true> keys whose assignment is part of a cycle */
    private array $cyclic = [];

    /**
     * @param PowerNode[] $nodes
     * @param PowerConnection[] $venueConnections
     * @param array<string, PowerAssignment> $assignments
     */
    public function calculate(array $nodes, array $venueConnections, array $assignments, float $defaultFactor): PowerLoadResult
    {
        $this->nodes = [];
        foreach ($nodes as $node) {
            $this->nodes[$node->key()] = $node;
        }
        $this->venueConnections = [];
        foreach ($venueConnections as $connection) {
            $this->venueConnections[$connection->id] = $connection;
        }
        $this->defaultFactor = $defaultFactor;
        $this->outletChildren = [];
        $this->venueChildren = [];
        $this->memo = [];
        $this->visiting = [];
        $this->warnings = [];
        $this->cyclic = [];
        $this->assignments = [];
        $this->factors = [];
        foreach ($assignments as $assignment) {
            if ($assignment->simultaneityFactor !== null) {
                $this->factors[$assignment->key()] = $assignment->simultaneityFactor;
            }
        }

        $this->resolveAssignments($assignments);
        $this->detectCycles();

        $checks = [];
        $distributorLoads = [];
        foreach ($this->nodes as $key => $node) {
            if ($node->distributor === null) {
                continue;
            }
            $load = $this->nodeLoad($key);
            $distributorLoads[$key] = $load['vector'];
            array_push($checks, ...$this->distributorChecks($node, $load));
        }

        $venueLoads = [];
        foreach ($this->venueConnections as $id => $connection) {
            if (!isset($this->venueChildren[$id])) {
                continue;
            }
            $vector = [0.0, 0.0, 0.0];
            $threePhase = $connection->type->isThreePhase();
            foreach ($this->venueChildren[$id] as $childKey) {
                $vector = self::add($vector, $this->place($childKey, $threePhase, 'L1'));
            }
            $venueLoads[$id] = $vector;
            $label = 'Anschluss ' . $connection->type->value . ($connection->location !== null ? ' (' . $connection->location . ')' : '');
            array_push($checks, ...$this->vectorChecks(LoadCheck::SCOPE_VENUE, $label, $vector, $threePhase, $connection->breakerRating->amps(), null, $id));
        }

        $unassigned = [];
        $totalW = 0.0;
        foreach ($this->nodes as $key => $node) {
            if ($node->distributor !== null) {
                continue;
            }
            $totalW += $this->effectivePower($key);
            if (!isset($this->assignments[$key]) || isset($this->cyclic[$key])) {
                if (($node->inputPowerW ?? 0) > 0) {
                    $unassigned[] = $key;
                }
            }
        }

        return new PowerLoadResult(
            checks: $checks,
            distributorLoads: $distributorLoads,
            venueLoads: $venueLoads,
            unassignedKeys: $unassigned,
            warnings: array_values(array_unique($this->warnings)),
            totalEffectivePowerW: $totalW,
            outletChildren: $this->outletChildren,
            venueChildren: $this->venueChildren,
            validAssignments: array_diff_key($this->assignments, $this->cyclic),
        );
    }

    /** Effective power (input power × simultaneity factor) of a consumer unit in watts. */
    public function effectivePower(string $key): float
    {
        $node = $this->nodes[$key] ?? null;
        if ($node === null || $node->distributor !== null) {
            return 0.0;
        }
        $factor = $this->factors[$key] ?? $this->defaultFactor;

        return max(0, $node->inputPowerW ?? 0) * $factor;
    }

    /** @param array<string, PowerAssignment> $assignments */
    private function resolveAssignments(array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $key = $assignment->key();
            $node = $this->nodes[$key] ?? null;
            if ($node === null) {
                continue; // unit no longer planned
            }
            if ($assignment->isOutlet()) {
                $targetKey = (string) $assignment->targetKey();
                $target = $this->nodes[$targetKey] ?? null;
                if ($target === null || $target->distributor === null) {
                    $this->warnings[] = $node->label . ': Der Stromverteiler, in dem es steckt, ist nicht mehr eingeplant.';
                    continue;
                }
                if ($targetKey === $key) {
                    $this->warnings[] = $node->label . ': Ein Stromverteiler kann nicht in sich selbst stecken.';
                    continue;
                }
                if ($this->outlet($target->distributor, (string) $assignment->outletId) === null) {
                    $this->warnings[] = $node->label . ': Der Abgang von ' . $target->label . ' existiert nicht mehr.';
                    continue;
                }
                $this->assignments[$key] = $assignment;
                $this->outletChildren[$targetKey][(string) $assignment->outletId][] = $key;
            } elseif ($assignment->isVenue()) {
                if (!isset($this->venueConnections[(string) $assignment->venueConnectionId])) {
                    $this->warnings[] = $node->label . ': Der Stromanschluss des Veranstaltungsorts existiert nicht mehr.';
                    continue;
                }
                $this->assignments[$key] = $assignment;
                $this->venueChildren[(string) $assignment->venueConnectionId][] = $key;
            }
        }
    }

    /** Distributors plugged into each other in a loop carry no load; the loop is reported. */
    private function detectCycles(): void
    {
        foreach ($this->assignments as $key => $assignment) {
            $seen = [$key => true];
            $current = $assignment->targetKey();
            while ($current !== null) {
                if (isset($seen[$current])) {
                    if ($current === $key) {
                        $this->cyclic[$key] = true;
                        $this->warnings[] = ($this->nodes[$key]->label ?? $key) . ': Stromverteiler stecken im Kreis ineinander – die Zuordnung wird ignoriert.';
                    }
                    break;
                }
                $seen[$current] = true;
                $current = ($this->assignments[$current] ?? null)?->targetKey();
            }
        }
        foreach (array_keys($this->cyclic) as $key) {
            $assignment = $this->assignments[$key];
            $targetKey = (string) $assignment->targetKey();
            $outletId = (string) $assignment->outletId;
            $this->outletChildren[$targetKey][$outletId] = array_values(array_diff($this->outletChildren[$targetKey][$outletId] ?? [], [$key]));
        }
    }

    /**
     * Load of a distributor in its internal phase coordinates.
     *
     * @return array{vector: float[], outlets: array<string, float[]>, placed: array<string, float[]>}
     */
    private function nodeLoad(string $key): array
    {
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }
        $node = $this->nodes[$key];
        $distributor = $node->distributor;
        $result = ['vector' => [0.0, 0.0, 0.0], 'outlets' => [], 'placed' => []];
        if ($distributor === null || isset($this->visiting[$key])) {
            return $result;
        }
        $this->visiting[$key] = true;
        $singleInlet = !$distributor->inletType->isThreePhase();
        foreach ($this->outletChildren[$key] ?? [] as $outletId => $children) {
            $outlet = $this->outlet($distributor, $outletId);
            if ($outlet === null) {
                continue;
            }
            $outletVector = [0.0, 0.0, 0.0];
            foreach ($children as $childKey) {
                if ($singleInlet) {
                    $placed = $this->place($childKey, false, 'L1');
                } elseif ($outlet->connectorType->isThreePhase()) {
                    $placed = $this->place($childKey, true, 'L1');
                } else {
                    $phase = $distributor->phaseOf($outlet) ?? $this->assignments[$childKey]->phase ?? null;
                    if ($phase === null) {
                        $this->warnings[] = $this->nodes[$childKey]->label . ': Die Phase am Abgang ' . self::outletName($outlet) . ' von ' . $node->label . ' ist unbekannt – bitte eine Phase wählen (gerechnet wird mit L1).';
                        $phase = 'L1';
                    }
                    $placed = $this->place($childKey, false, $phase);
                }
                $result['placed'][$childKey] = $placed;
                $outletVector = self::add($outletVector, $placed);
            }
            $result['outlets'][$outletId] = $outletVector;
            $result['vector'] = self::add($result['vector'], $outletVector);
        }
        unset($this->visiting[$key]);

        return $this->memo[$key] = $result;
    }

    /**
     * Load of a unit as seen at its supply, in phase coordinates of the
     * supply (three-phase supply or single phase $phase).
     *
     * @return float[]
     */
    private function place(string $key, bool $threePhaseSupply, string $phase): array
    {
        $node = $this->nodes[$key];
        $assignment = $this->assignments[$key] ?? null;
        if ($node->distributor === null) {
            $power = $this->effectivePower($key);
            if ($threePhaseSupply && $assignment?->phase === null) {
                return [$power / 3, $power / 3, $power / 3];
            }

            return self::onPhase($threePhaseSupply ? (string) $assignment?->phase : $phase, $power);
        }
        $load = $this->nodeLoad($key);
        $total = array_sum($load['vector']);
        if (!$threePhaseSupply) {
            return self::onPhase($phase, $total);
        }
        if ($node->distributor->inletType->isThreePhase()) {
            return $load['vector'];
        }
        if ($assignment?->phase === null) {
            $this->warnings[] = $node->label . ': einphasige Zuleitung an dreiphasigem Abgang – bitte eine Phase wählen (gerechnet wird mit L1).';
        }

        return self::onPhase($assignment?->phase ?? 'L1', $total);
    }

    /**
     * @param array{vector: float[], outlets: array<string, float[]>, placed: array<string, float[]>} $load
     * @return LoadCheck[]
     */
    private function distributorChecks(PowerNode $node, array $load): array
    {
        $distributor = $node->distributor;
        assert($distributor instanceof PowerDistributor);
        $key = $node->key();
        $checks = $this->vectorChecks(
            LoadCheck::SCOPE_INLET,
            $node->label . ' · Zuleitung ' . $distributor->inletType->shortLabel(),
            $load['vector'],
            $distributor->inletType->isThreePhase(),
            $distributor->inletType->ratedCurrentA(),
            $key,
            null,
        );

        foreach ($distributor->devices as $device) {
            if ($device->ratedCurrentA === null) {
                continue;
            }
            $vector = [0.0, 0.0, 0.0];
            foreach ($distributor->outlets as $outlet) {
                if (isset($load['outlets'][$outlet->id]) && $this->isBehind($distributor, $outlet, $device->id)) {
                    $vector = self::add($vector, $load['outlets'][$outlet->id]);
                }
            }
            if (array_sum($vector) <= 0) {
                continue;
            }
            $threePhase = $distributor->inletType->isThreePhase() && $device->suppliesThreePhase() !== false;
            $label = $node->label . ' · ' . $device->label . ' (' . $device->specification() . ')';
            array_push($checks, ...$this->vectorChecks(LoadCheck::SCOPE_DEVICE, $label, $vector, $threePhase, $device->ratedCurrentA, $key, $device->id));
        }

        foreach ($distributor->outlets as $outlet) {
            foreach ($this->outletChildren[$key][$outlet->id] ?? [] as $childKey) {
                $placed = $load['placed'][$childKey] ?? null;
                if ($placed === null) {
                    continue;
                }
                $phaseIndex = array_keys($placed, max($placed), true)[0];
                $threePhase = $outlet->connectorType->isThreePhase() && $distributor->inletType->isThreePhase();
                $checks[] = new LoadCheck(
                    LoadCheck::SCOPE_OUTLET,
                    $node->label . ' · Abgang ' . self::outletName($outlet) . ' → ' . $this->nodes[$childKey]->label,
                    $threePhase ? self::PHASES[$phaseIndex] : null,
                    max($placed) / self::VOLTAGE,
                    $outlet->connectorType->ratedCurrentA(),
                    $key,
                    $outlet->id,
                );
            }
            $count = count($this->outletChildren[$key][$outlet->id] ?? []);
            if ($count > $outlet->quantity) {
                $this->warnings[] = $node->label . ': Am Abgang ' . self::outletName($outlet) . ' stecken ' . $count . ' Geräte, es gibt aber nur ' . $outlet->quantity . ' Steckdose(n).';
            }
        }

        return $checks;
    }

    /**
     * @param float[] $vector
     * @return LoadCheck[]
     */
    private function vectorChecks(string $scope, string $label, array $vector, bool $threePhase, ?int $limitA, ?string $nodeKey, ?string $elementId): array
    {
        if (!$threePhase) {
            return [new LoadCheck($scope, $label, null, array_sum($vector) / self::VOLTAGE, $limitA, $nodeKey, $elementId)];
        }
        $checks = [];
        foreach (self::PHASES as $i => $phase) {
            $checks[] = new LoadCheck($scope, $label, $phase, $vector[$i] / self::VOLTAGE, $limitA, $nodeKey, $elementId);
        }

        return $checks;
    }

    /** Is the outlet protected by the device (directly or as upstream RCD of its MCB)? */
    private function isBehind(PowerDistributor $distributor, Outlet $outlet, string $deviceId): bool
    {
        if ($outlet->deviceId === null) {
            return false;
        }
        if ($outlet->deviceId === $deviceId) {
            return true;
        }

        return $distributor->device($outlet->deviceId)?->upstreamDeviceId === $deviceId;
    }

    private function outlet(PowerDistributor $distributor, string $outletId): ?Outlet
    {
        foreach ($distributor->outlets as $outlet) {
            if ($outlet->id === $outletId) {
                return $outlet;
            }
        }

        return null;
    }

    public static function outletName(Outlet $outlet): string
    {
        return $outlet->label !== null ? '„' . $outlet->label . '“ (' . $outlet->connectorType->shortLabel() . ')' : $outlet->connectorType->shortLabel();
    }

    /** @return float[] */
    private static function onPhase(string $phase, float $power): array
    {
        $vector = [0.0, 0.0, 0.0];
        $index = array_search($phase, self::PHASES, true);
        $vector[$index === false ? 0 : $index] = $power;

        return $vector;
    }

    /**
     * @param float[] $a
     * @param float[] $b
     * @return float[]
     */
    private static function add(array $a, array $b): array
    {
        return [$a[0] + $b[0], $a[1] + $b[1], $a[2] + $b[2]];
    }
}
