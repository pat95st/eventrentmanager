<?php

declare(strict_types=1);

namespace Tests\Unit\Application\AnalogMulticore;

use App\Application\AnalogMulticore\AnalogMulticoreBuilder;
use App\Application\AnalogMulticore\AnalogMulticoreInput;
use Codeception\Test\Unit;

final class AnalogMulticoreBuilderTest extends Unit
{
    public function testBuildsSendsReturnsAndFlags(): void
    {
        [$errors, $multicore] = (new AnalogMulticoreBuilder())->build('article-1', new AnalogMulticoreInput('24', ' 8 ', true, true));

        $this->assertSame([], $errors);
        $this->assertNotNull($multicore);
        $this->assertSame('article-1', $multicore->articleId);
        $this->assertSame(24, $multicore->sends);
        $this->assertSame(8, $multicore->returns);
        $this->assertTrue($multicore->onDrum);
        $this->assertTrue($multicore->hasHarting);
    }

    public function testEmptyFieldsCountAsZero(): void
    {
        [$errors, $multicore] = (new AnalogMulticoreBuilder())->build('article-1', new AnalogMulticoreInput('16', ''));

        $this->assertSame([], $errors);
        $this->assertSame(16, $multicore->sends);
        $this->assertSame(0, $multicore->returns);
        $this->assertFalse($multicore->onDrum);
        $this->assertFalse($multicore->hasHarting);
    }

    public function testRejectsInvalidCounts(): void
    {
        [$errors, $multicore] = (new AnalogMulticoreBuilder())->build('article-1', new AnalogMulticoreInput('-1', '1000'));

        $this->assertNull($multicore);
        $this->assertArrayHasKey('multicore_sends', $errors);
        $this->assertArrayHasKey('multicore_returns', $errors);

        [$errors] = (new AnalogMulticoreBuilder())->build('article-1', new AnalogMulticoreInput('2,5', 'abc'));
        $this->assertArrayHasKey('multicore_sends', $errors);
        $this->assertArrayHasKey('multicore_returns', $errors);
    }

    public function testRequiresAtLeastOneChannel(): void
    {
        [$errors, $multicore] = (new AnalogMulticoreBuilder())->build('article-1', new AnalogMulticoreInput('0', null, true));

        $this->assertNull($multicore);
        $this->assertArrayHasKey('multicore_sends', $errors);
    }
}
