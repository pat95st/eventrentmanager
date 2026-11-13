<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Dashboard;

use App\Domain\Dashboard\DashboardLayout;
use Codeception\Test\Unit;

final class DashboardLayoutTest extends Unit
{
    public function testKeepsOrderAndDropsUnknownZonesDuplicatesAndNonStrings(): void
    {
        $layout = DashboardLayout::fromZones([
            'main' => ['b', 'a', 'b', 7, ''],
            'side' => ['a', 'c'],
            'elsewhere' => ['d'],
            'bottom' => 'kein Array',
        ]);

        $this->assertSame(['main' => ['b', 'a'], 'side' => ['c'], 'bottom' => []], $layout->zones());
        $this->assertSame(['b', 'a', 'c'], $layout->keys());
        $this->assertSame('side', $layout->zoneOf('c'));
        $this->assertNull($layout->zoneOf('d'));
    }

    public function testOnlyRemovesEverythingElse(): void
    {
        $layout = DashboardLayout::fromZones(['main' => ['a', 'b'], 'bottom' => ['c']])->only(['b', 'c']);

        $this->assertSame(['main' => ['b'], 'side' => [], 'bottom' => ['c']], $layout->zones());
        $this->assertTrue($layout->only([])->isEmpty());
    }

    public function testJsonRoundTripAndUnreadableJson(): void
    {
        $layout = DashboardLayout::fromZones(['side' => ['a'], 'bottom' => ['stat:x']]);

        $this->assertSame($layout->zones(), DashboardLayout::fromJson($layout->toJson())?->zones());
        $this->assertNull(DashboardLayout::fromJson(null));
        $this->assertNull(DashboardLayout::fromJson(''));
        $this->assertNull(DashboardLayout::fromJson('{kaputt'));
    }
}
