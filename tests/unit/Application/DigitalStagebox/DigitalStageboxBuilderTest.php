<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DigitalStagebox;

use App\Application\DigitalStagebox\DigitalStageboxBuilder;
use App\Application\DigitalStagebox\DigitalStageboxInput;
use App\Domain\DigitalStagebox\DigitalProtocol;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryDigitalStageboxRepository;

final class DigitalStageboxBuilderTest extends Unit
{
    private DigitalStageboxBuilder $builder;

    protected function _before(): void
    {
        $repository = new InMemoryDigitalStageboxRepository();
        $repository->saveProtocol(new DigitalProtocol('dante', 'Dante', 10));
        $this->builder = new DigitalStageboxBuilder($repository);
    }

    public function testCombinesXlrAndComboInputsAndAnalogAndAesOutputs(): void
    {
        [$errors, $stagebox] = $this->builder->build('article-1', new DigitalStageboxInput('dante', '24', '8', '12', '4'));

        $this->assertSame([], $errors);
        $this->assertNotNull($stagebox);
        $this->assertSame('dante', $stagebox->protocolId);
        $this->assertSame(24, $stagebox->inputsXlr);
        $this->assertSame(8, $stagebox->inputsCombo);
        $this->assertSame(12, $stagebox->outputsAnalog);
        $this->assertSame(4, $stagebox->outputsAes);
        $this->assertSame(32, $stagebox->totalInputs());
        $this->assertSame(16, $stagebox->totalOutputs());
    }

    public function testEmptyFieldsCountAsZero(): void
    {
        [$errors, $stagebox] = $this->builder->build('article-1', new DigitalStageboxInput('dante', '16', '', null, ' 8 '));

        $this->assertSame([], $errors);
        $this->assertSame(16, $stagebox->inputsXlr);
        $this->assertSame(0, $stagebox->inputsCombo);
        $this->assertSame(0, $stagebox->outputsAnalog);
        $this->assertSame(8, $stagebox->outputsAes);
    }

    public function testRequiresKnownProtocol(): void
    {
        [$errors, $stagebox] = $this->builder->build('article-1', new DigitalStageboxInput('unbekannt', '16', '', '8', ''));

        $this->assertNull($stagebox);
        $this->assertArrayHasKey('stagebox_protocol_id', $errors);

        [$errors] = $this->builder->build('article-1', new DigitalStageboxInput(null, '16', '', '8', ''));
        $this->assertArrayHasKey('stagebox_protocol_id', $errors);
    }

    public function testRejectsInvalidCounts(): void
    {
        [$errors, $stagebox] = $this->builder->build('article-1', new DigitalStageboxInput('dante', '-1', '2,5', '1000', 'abc'));

        $this->assertNull($stagebox);
        $this->assertArrayHasKey('stagebox_inputs_xlr', $errors);
        $this->assertArrayHasKey('stagebox_inputs_combo', $errors);
        $this->assertArrayHasKey('stagebox_outputs_analog', $errors);
        $this->assertArrayHasKey('stagebox_outputs_aes', $errors);
    }

    public function testRequiresAtLeastOneInputOrOutput(): void
    {
        [$errors, $stagebox] = $this->builder->build('article-1', new DigitalStageboxInput('dante', '0', '', '', '0'));

        $this->assertNull($stagebox);
        $this->assertArrayHasKey('stagebox_inputs_xlr', $errors);
    }
}
