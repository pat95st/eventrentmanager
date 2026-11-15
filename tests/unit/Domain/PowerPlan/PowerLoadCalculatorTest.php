<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PowerPlan;

use App\Domain\PowerDistributor\ConnectorType;
use App\Domain\PowerDistributor\Outlet;
use App\Domain\PowerDistributor\PowerDistributor;
use App\Domain\PowerDistributor\ProtectiveDevice;
use App\Domain\PowerDistributor\ProtectiveDeviceType;
use App\Domain\PowerPlan\LoadCheck;
use App\Domain\PowerPlan\PowerAssignment;
use App\Domain\PowerPlan\PowerLoadCalculator;
use App\Domain\PowerPlan\PowerNode;
use App\Domain\PowerPlan\PowerRebalancer;
use App\Domain\Venue\CircuitBreakerRating;
use App\Domain\Venue\PowerConnection;
use App\Domain\Venue\PowerConnectionType;
use App\Domain\Venue\RcdTripCurrent;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class PowerLoadCalculatorTest extends Unit
{
    /**
     * CEE 32 A red inlet, three 1-pole MCBs C16 (one per phase), each with
     * three Schuko outlets.
     */
    private function distributor(string $articleId = 'dist'): PowerDistributor
    {
        $devices = [];
        $outlets = [];
        foreach (['L1', 'L2', 'L3'] as $i => $phase) {
            $devices[] = new ProtectiveDevice('mcb-' . $phase, 'F' . ($i + 1), ProtectiveDeviceType::Mcb, 1, null, null, 16, 'C', null, $i, null, false, $phase);
            $outlets[] = new Outlet('out-' . $phase, ConnectorType::Schuko, 3, $phase, 'mcb-' . $phase, $i);
        }
        $outlets[] = new Outlet('out-cee', ConnectorType::CeeRed16, 1, 'CEE', null, 3);

        return new PowerDistributor($articleId, ConnectorType::CeeRed32, null, $devices, $outlets);
    }

    private function venue(PowerConnectionType $type = PowerConnectionType::Cee32A400V, CircuitBreakerRating $rating = CircuitBreakerRating::A32): PowerConnection
    {
        return new PowerConnection('venue', 'v', $type, $rating, RcdTripCurrent::Ma30, null, new DateTimeImmutable());
    }

    private function onOutlet(string $articleId, int $unit, string $outletId, ?string $phase = null, ?float $factor = null): PowerAssignment
    {
        return new PowerAssignment($articleId, $unit, 'dist', 1, $outletId, null, $phase, $factor);
    }

    /** @param LoadCheck[] $checks */
    private function current(array $checks, string $scope, ?string $phase, ?string $elementId = null): float
    {
        foreach ($checks as $check) {
            if ($check->scope === $scope && $check->phase === $phase && ($elementId === null || $check->elementId === $elementId)) {
                return round($check->currentA, 2);
            }
        }
        $this->fail('Check not found: ' . $scope . ' ' . $phase);
    }

    public function testSinglePhaseConsumersLandOnThePhaseOfTheirOutlet(): void
    {
        $nodes = [
            new PowerNode('dist', 1, 'Verteiler', null, $this->distributor()),
            new PowerNode('amp', 1, 'Endstufe #1', 2300),
            new PowerNode('amp', 2, 'Endstufe #2', 2300),
            new PowerNode('light', 1, 'Scheinwerfer', 460),
        ];
        $assignments = [
            'dist#1' => new PowerAssignment('dist', 1, null, null, null, 'venue'),
            'amp#1' => $this->onOutlet('amp', 1, 'out-L1'),
            'amp#2' => $this->onOutlet('amp', 2, 'out-L1'),
            'light#1' => $this->onOutlet('light', 1, 'out-L2'),
        ];

        $result = (new PowerLoadCalculator())->calculate($nodes, [$this->venue()], $assignments, 1.0);

        $this->assertSame([4600.0, 460.0, 0.0], $result->distributorLoads['dist#1']);
        $this->assertSame(20.0, $this->current($result->checks, LoadCheck::SCOPE_INLET, 'L1'));
        $this->assertSame(20.0, $this->current($result->checks, LoadCheck::SCOPE_DEVICE, null, 'mcb-L1'));
        $this->assertSame(20.0, $this->current($result->checks, LoadCheck::SCOPE_VENUE, 'L1'));
        $this->assertSame(2.0, $this->current($result->checks, LoadCheck::SCOPE_DEVICE, null, 'mcb-L2'));
        // MCB F1 (C16) is overloaded by 20 A, inlet (32 A) is not.
        $overloads = $result->overloads();
        $this->assertCount(1, $overloads);
        $this->assertSame('mcb-L1', $overloads[0]->elementId);
        $this->assertSame(4600.0 + 460.0, $result->totalEffectivePowerW);
    }

    public function testSimultaneityFactorReducesTheLoad(): void
    {
        $nodes = [
            new PowerNode('dist', 1, 'Verteiler', null, $this->distributor()),
            new PowerNode('amp', 1, 'Endstufe #1', 2300),
            new PowerNode('amp', 2, 'Endstufe #2', 2300),
        ];
        $assignments = [
            'amp#1' => $this->onOutlet('amp', 1, 'out-L1'),
            'amp#2' => $this->onOutlet('amp', 2, 'out-L1', null, 0.2),
        ];

        $result = (new PowerLoadCalculator())->calculate($nodes, [], $assignments, 0.5);

        // 2300 W × 0.5 + 2300 W × 0.2 = 1610 W = 7 A
        $this->assertSame(7.0, $this->current($result->checks, LoadCheck::SCOPE_DEVICE, null, 'mcb-L1'));
        $this->assertFalse($result->hasOverloads());
    }

    public function testThreePhaseConsumerIsSplitEvenly(): void
    {
        $nodes = [
            new PowerNode('dist', 1, 'Verteiler', null, $this->distributor()),
            new PowerNode('heater', 1, 'Heizlüfter', 6900),
        ];
        $result = (new PowerLoadCalculator())->calculate($nodes, [], ['heater#1' => $this->onOutlet('heater', 1, 'out-cee')], 1.0);

        $this->assertSame([2300.0, 2300.0, 2300.0], $result->distributorLoads['dist#1']);
        $this->assertSame(10.0, $this->current($result->checks, LoadCheck::SCOPE_OUTLET, 'L1'));
    }

    public function testDownstreamSinglePhaseDistributorLoadsTheChosenPhase(): void
    {
        $sub = new PowerDistributor('sub', ConnectorType::Schuko, null, [], [new Outlet('sub-out', ConnectorType::Schuko, 6, null, null)]);
        $nodes = [
            new PowerNode('dist', 1, 'Verteiler', null, $this->distributor()),
            new PowerNode('sub', 1, 'Steckdosenleiste', null, $sub),
            new PowerNode('led', 1, 'LED-Bar', 230),
        ];
        $assignments = [
            'sub#1' => $this->onOutlet('sub', 1, 'out-L3'),
            'led#1' => new PowerAssignment('led', 1, 'sub', 1, 'sub-out'),
        ];

        $result = (new PowerLoadCalculator())->calculate($nodes, [], $assignments, 1.0);

        $this->assertSame([0.0, 0.0, 230.0], $result->distributorLoads['dist#1']);
        $this->assertSame([], $result->unassignedKeys);
    }

    public function testCyclesAndUnassignedUnitsAreReported(): void
    {
        $a = $this->distributor('a');
        $b = $this->distributor('b');
        $nodes = [
            new PowerNode('a', 1, 'A', null, $a),
            new PowerNode('b', 1, 'B', null, $b),
            new PowerNode('amp', 1, 'Endstufe', 1000),
        ];
        $assignments = [
            'a#1' => new PowerAssignment('a', 1, 'b', 1, 'out-cee'),
            'b#1' => new PowerAssignment('b', 1, 'a', 1, 'out-cee'),
        ];

        $result = (new PowerLoadCalculator())->calculate($nodes, [], $assignments, 1.0);

        $this->assertSame(['amp#1'], $result->unassignedKeys);
        $this->assertNotEmpty(array_filter($result->warnings, static fn (string $w) => str_contains($w, 'im Kreis')));
    }

    public function testRebalancerMovesLoadToAnotherPhase(): void
    {
        $nodes = [
            new PowerNode('dist', 1, 'Verteiler', null, $this->distributor()),
            new PowerNode('amp', 1, 'Endstufe #1', 2300),
            new PowerNode('amp', 2, 'Endstufe #2', 2300),
            new PowerNode('amp', 3, 'Endstufe #3', 1150),
        ];
        $assignments = [
            'amp#1' => $this->onOutlet('amp', 1, 'out-L1'),
            'amp#2' => $this->onOutlet('amp', 2, 'out-L1'),
            'amp#3' => $this->onOutlet('amp', 3, 'out-L1'),
        ];

        ['moves' => $moves, 'result' => $result] = (new PowerRebalancer())->suggest($nodes, [], $assignments, 1.0);

        $this->assertFalse($result->hasOverloads());
        $this->assertNotEmpty($moves);
        foreach ($moves as $move) {
            $this->assertNotSame('out-L1', $move->to->outletId);
        }
    }

    public function testRebalancerRespectsFreeSocketsAndConnectorType(): void
    {
        // Only one Schuko socket per phase; the CEE outlet does not take a Schuko plug.
        $devices = [];
        $outlets = [];
        foreach (['L1', 'L2'] as $i => $phase) {
            $devices[] = new ProtectiveDevice('mcb-' . $phase, 'F' . ($i + 1), ProtectiveDeviceType::Mcb, 1, null, null, 16, 'C', null, $i, null, false, $phase);
            $outlets[] = new Outlet('out-' . $phase, ConnectorType::Schuko, 1, $phase, 'mcb-' . $phase, $i);
        }
        $outlets[] = new Outlet('out-cee', ConnectorType::CeeRed16, 1, null, null, 3);
        $distributor = new PowerDistributor('dist', ConnectorType::CeeRed32, null, $devices, $outlets);
        $nodes = [
            new PowerNode('dist', 1, 'Verteiler', null, $distributor),
            new PowerNode('amp', 1, 'Endstufe #1', 2300),
            new PowerNode('amp', 2, 'Endstufe #2', 2300),
        ];
        $assignments = [
            'amp#1' => $this->onOutlet('amp', 1, 'out-L1'),
            'amp#2' => $this->onOutlet('amp', 2, 'out-L1'),
        ];

        ['moves' => $moves, 'result' => $result] = (new PowerRebalancer())->suggest($nodes, [], $assignments, 1.0);

        $this->assertCount(1, $moves);
        $this->assertSame('out-L2', $moves[0]->to->outletId);
        $this->assertFalse($result->hasOverloads());
    }

    public function testRebalancerFindsNothingWhenNoFreeOutletFits(): void
    {
        $nodes = [
            new PowerNode('dist', 1, 'Verteiler', null, $this->distributor()),
            new PowerNode('heater', 1, 'Heizung', 23000),
        ];

        ['moves' => $moves, 'result' => $result] = (new PowerRebalancer())->suggest($nodes, [], ['heater#1' => $this->onOutlet('heater', 1, 'out-cee')], 1.0);

        $this->assertSame([], $moves);
        $this->assertTrue($result->hasOverloads());
    }
}
