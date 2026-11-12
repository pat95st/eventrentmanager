<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\RevenueGoal;

use App\Domain\RevenueGoal\RevenueGoal;
use App\Domain\RevenueGoal\RevenueGoalKind;
use App\Domain\RevenueGoal\RevenueGoalProgress;
use Codeception\Test\Unit;

final class RevenueGoalTest extends Unit
{
    public function testDefaultAmountAppliesToEveryYearUnlessOverridden(): void
    {
        $goal = new RevenueGoal('g1', 'Liebhabereigrenze', RevenueGoalKind::Target, '5000.00');
        $goal->setYearAmount(2026, '6500.00');

        $this->assertSame('5000.00', $goal->amountFor(2025));
        $this->assertSame('6500.00', $goal->amountFor(2026));
        $this->assertSame('5000.00', $goal->amountFor(2027));
        $this->assertTrue($goal->hasOverrideFor(2026));

        $goal->setYearAmount(2026, null);
        $this->assertSame('5000.00', $goal->amountFor(2026));
        $this->assertFalse($goal->hasOverrideFor(2026));
    }

    public function testTargetIsOpenUntilReached(): void
    {
        $goal = new RevenueGoal('g1', 'Jahresziel', RevenueGoalKind::Target, '10000.00');

        $open = new RevenueGoalProgress($goal, 2026, 2500.0);
        $this->assertSame(RevenueGoalProgress::STATUS_OPEN, $open->status());
        $this->assertSame(25.0, $open->percent());
        $this->assertSame(7500.0, $open->remaining());

        $reached = new RevenueGoalProgress($goal, 2026, 12000.0);
        $this->assertSame(RevenueGoalProgress::STATUS_REACHED, $reached->status());
        $this->assertSame(-2000.0, $reached->remaining());
    }

    public function testLimitWarnsFromEightyPercentAndUsesTheYearAmount(): void
    {
        $goal = new RevenueGoal('g1', 'Kleinunternehmergrenze', RevenueGoalKind::Limit, '25000.00', [2025 => '22000.00']);

        $this->assertSame(RevenueGoalProgress::STATUS_OK, (new RevenueGoalProgress($goal, 2026, 19999.0))->status());
        $this->assertSame(RevenueGoalProgress::STATUS_WARNING, (new RevenueGoalProgress($goal, 2026, 20000.0))->status());
        $this->assertSame(RevenueGoalProgress::STATUS_WARNING, (new RevenueGoalProgress($goal, 2026, 25000.0))->status());
        $this->assertSame(RevenueGoalProgress::STATUS_EXCEEDED, (new RevenueGoalProgress($goal, 2026, 25000.01))->status());
        $this->assertSame(RevenueGoalProgress::STATUS_EXCEEDED, (new RevenueGoalProgress($goal, 2025, 23000.0))->status());
    }

    public function testZeroAmountDoesNotDivideByZero(): void
    {
        $goal = new RevenueGoal('g1', 'Null', RevenueGoalKind::Target, '0.00');

        $this->assertSame(0.0, (new RevenueGoalProgress($goal, 2026, 100.0))->percent());
    }
}
