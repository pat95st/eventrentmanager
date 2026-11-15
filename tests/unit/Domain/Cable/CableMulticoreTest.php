<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cable;

use App\Domain\Cable\CableMulticore;
use App\Domain\Cable\CableType;
use Codeception\Test\Unit;

final class CableMulticoreTest extends Unit
{
    public function testSummaries(): void
    {
        $this->assertSame('24/8', (new CableMulticore(24, 8))->channelSummary());
        $this->assertSame('24 Sends / 8 Returns · Trommel · Harting', (new CableMulticore(24, 8, true, true))->summary());
        $this->assertSame('12 Sends / 0 Returns', (new CableMulticore(12, 0))->summary());
    }

    public function testMulticoreTypeOverridesStoredInspectionFlag(): void
    {
        $this->assertFalse((new CableType('t', 'Multicore', null, true, null, [], isAnalogMulticore: true))->requiresInspection());
        $this->assertTrue((new CableType('t', 'H07RN-F', null, true, null, []))->requiresInspection());
    }
}
