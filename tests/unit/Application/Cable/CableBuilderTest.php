<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cable;

use App\Application\Cable\CableBuilder;
use App\Application\Cable\CableInput;
use App\Domain\Cable\CableType;
use App\Domain\Cable\ConnectorType;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryCableRepository;

final class CableBuilderTest extends Unit
{
    private InMemoryCableRepository $cables;

    private CableBuilder $builder;

    protected function _before(): void
    {
        $this->cables = new InMemoryCableRepository();
        $this->cables->saveConnector(new ConnectorType('harting-m', 'Harting-Stecker'));
        $this->cables->saveConnector(new ConnectorType('box', 'Multicore-Anschlusskasten'));
        $this->cables->saveType(new CableType('mc', 'Analoges Multicore', null, false, null, ['harting-m', 'box'], isAnalogMulticore: true));
        $this->cables->saveType(new CableType('mic', 'Mikrofonkabel', null, false, null, ['harting-m', 'box']));
        $this->builder = new CableBuilder($this->cables);
    }

    public function testBuildsMulticoreWithLengthSendsReturnsAndFlags(): void
    {
        $input = new CableInput('mc', '30,5', 'harting-m', 'box', '24', ' 8 ', true, true);
        [$errors, $cable, $type] = $this->builder->build('a1', $input);

        $this->assertSame([], $errors);
        $this->assertSame('30.50', $cable->lengthM);
        $this->assertNotNull($cable->multicore);
        $this->assertSame(24, $cable->multicore->sends);
        $this->assertSame(8, $cable->multicore->returns);
        $this->assertTrue($cable->multicore->onDrum);
        $this->assertTrue($cable->multicore->hasHarting);
        $this->assertSame(
            'Analoges Multicore 24/8 30,5 m Harting-Stecker / Multicore-Anschlusskasten',
            CableBuilder::suggestName($type, $cable, $this->cables->findConnectorById('harting-m'), $this->cables->findConnectorById('box')),
        );
    }

    public function testMulticoreEmptyCountsAreZeroButOneChannelIsRequired(): void
    {
        [$errors, $cable] = $this->builder->build('a1', new CableInput('mc', '10', 'harting-m', 'box', '16', ''));
        $this->assertSame([], $errors);
        $this->assertSame(0, $cable->multicore->returns);
        $this->assertFalse($cable->multicore->onDrum);

        [$errors, $cable] = $this->builder->build('a1', new CableInput('mc', '10', 'harting-m', 'box', '0', null));
        $this->assertNull($cable);
        $this->assertArrayHasKey('multicore_sends', $errors);
    }

    public function testMulticoreRejectsInvalidCounts(): void
    {
        [$errors, $cable] = $this->builder->build('a1', new CableInput('mc', '10', 'harting-m', 'box', '-1', '1000'));

        $this->assertNull($cable);
        $this->assertArrayHasKey('multicore_sends', $errors);
        $this->assertArrayHasKey('multicore_returns', $errors);
    }

    public function testOrdinaryCableIgnoresMulticoreFields(): void
    {
        [$errors, $cable] = $this->builder->build('a1', new CableInput('mic', '5', 'harting-m', 'box', 'abc', null, true));

        $this->assertSame([], $errors);
        $this->assertNull($cable->multicore);
    }
}
