<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PowerPlan;

use App\Domain\PowerPlan\ArticlePower;
use Codeception\Test\Unit;

final class ArticlePowerTest extends Unit
{
    public function testStatesPeakPowerWhenNoAverageIsRecorded(): void
    {
        $power = new ArticlePower(1200, null);

        self::assertSame(1200, $power->statedW());
        self::assertFalse($power->isAverage());
    }

    public function testStatesOnlyAveragePowerWhenRecorded(): void
    {
        $power = new ArticlePower(1200, 400);

        self::assertSame(400, $power->statedW());
        self::assertTrue($power->isAverage());
    }

    public function testAverageWithoutPeakIsStated(): void
    {
        self::assertSame(400, (new ArticlePower(null, 400))->statedW());
    }

    public function testUnknownPowerStatesNothing(): void
    {
        $power = new ArticlePower(null, null);

        self::assertNull($power->statedW());
        self::assertFalse($power->isAverage());
    }
}
