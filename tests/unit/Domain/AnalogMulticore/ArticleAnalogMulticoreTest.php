<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AnalogMulticore;

use App\Domain\AnalogMulticore\ArticleAnalogMulticore;
use Codeception\Test\Unit;

final class ArticleAnalogMulticoreTest extends Unit
{
    public function testSummaryWithDrumAndHarting(): void
    {
        $multicore = new ArticleAnalogMulticore('a', 24, 8, true, true);

        $this->assertSame(32, $multicore->totalChannels());
        $this->assertSame('24/8', $multicore->channelSummary());
        $this->assertSame('24 Sends / 8 Returns · Trommel · Harting', $multicore->summary());
    }

    public function testSummaryWithoutFlags(): void
    {
        $multicore = new ArticleAnalogMulticore('a', 12, 0);

        $this->assertSame('12 Sends / 0 Returns', $multicore->summary());
    }
}
