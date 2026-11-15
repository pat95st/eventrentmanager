<?php

declare(strict_types=1);

namespace Tests\Unit\Application\MixingConsole;

use App\Application\MixingConsole\MixingConsoleBuilder;
use App\Application\MixingConsole\MixingConsoleInput;
use App\Domain\DigitalStagebox\DigitalProtocol;
use App\Domain\MixingConsole\MixingConsoleChannelKind;
use App\Domain\MixingConsole\MixingConsoleType;
use App\Domain\MixingConsole\StageboxCompatibility;
use App\Domain\MixingConsole\StageboxOption;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryDigitalStageboxRepository;
use Tests\Support\Fakes\InMemoryMixingConsoleRepository;

final class MixingConsoleBuilderTest extends Unit
{
    private MixingConsoleBuilder $builder;

    protected function _before(): void
    {
        $protocols = new InMemoryDigitalStageboxRepository();
        $protocols->saveProtocol(new DigitalProtocol('aes50', 'AES50', 10));
        $protocols->saveProtocol(new DigitalProtocol('dante', 'Dante', 20));
        $protocols->saveProtocol(new DigitalProtocol('madi', 'MADI', 30));
        $consoles = new InMemoryMixingConsoleRepository();
        $consoles->addStageboxOption(new StageboxOption('sb-own', 'S16', 'm-console', 'Behringer', 'aes50'));
        $consoles->addStageboxOption(new StageboxOption('sb-other', 'DL32', 'm-other', 'Midas', 'aes50'));
        $consoles->addStageboxOption(new StageboxOption('sb-dante', 'Rio1608', 'm-other', 'Yamaha', 'dante'));
        $consoles->addStageboxOption(new StageboxOption('sb-madi', 'MADI box', 'm-console', 'Behringer', 'madi'));
        $this->builder = new MixingConsoleBuilder($consoles, $protocols);
    }

    public function testRequiresType(): void
    {
        [$errors, $console] = $this->builder->build('a1', new MixingConsoleInput(null));

        $this->assertNull($console);
        $this->assertArrayHasKey('mc_type', $errors);
    }

    public function testBuildsAnalogConsole(): void
    {
        [$errors, $console] = $this->builder->build('a1', new MixingConsoleInput(
            'analog',
            micInputs: '16',
            stereoInputs: '4',
            comboInputs: '',
            auxPre: '2',
            auxPost: '2',
            auxSwitchable: '2',
            subgroups: '4',
            hasMonoMaster: true,
            // digital fields are ignored for analog consoles
            localInputs: '8',
            interfaces: [['protocol_id' => 'dante', 'quantity' => '1']],
        ));

        $this->assertSame([], $errors);
        $this->assertNotNull($console);
        $this->assertSame(MixingConsoleType::Analog, $console->type);
        $this->assertSame(20, $console->totalAnalogInputs());
        $this->assertSame(6, $console->totalAux());
        $this->assertSame(4, $console->subgroups);
        $this->assertTrue($console->hasMonoMaster);
        $this->assertSame(0, $console->localInputs);
        $this->assertSame([], $console->interfaces);
        $this->assertSame('6 (2 × Pre, 2 × Post, 2 × umschaltbar)', $console->auxLabel());
    }

    public function testAnalogConsoleNeedsAnInputAndValidCounts(): void
    {
        [$errors] = $this->builder->build('a1', new MixingConsoleInput('analog'));
        $this->assertArrayHasKey('mc_mic_inputs', $errors);

        [$errors] = $this->builder->build('a1', new MixingConsoleInput('analog', micInputs: '8', auxPre: 'x', subgroups: '1000'));
        $this->assertArrayHasKey('mc_aux_pre', $errors);
        $this->assertArrayHasKey('mc_subgroups', $errors);
    }

    public function testBuildsDigitalConsoleAndSkipsEmptyRows(): void
    {
        [$errors, $console] = $this->builder->build('a1', new MixingConsoleInput(
            'digital',
            micInputs: '16', // analog fields are ignored for digital consoles
            localInputs: '16',
            localOutputs: '8',
            expansionSlots: '1',
            expansionSlotType: ' Option slot ',
            interfaces: [['protocol_id' => 'aes50', 'quantity' => '2'], ['protocol_id' => '', 'quantity' => '']],
            channels: [
                ['kind' => 'input', 'quantity' => '40', 'stereo' => '', 'label' => 'Input Channel'],
                ['kind' => 'aux', 'quantity' => '16', 'stereo' => '', 'label' => 'Mix Bus'],
                ['kind' => 'fx_return', 'quantity' => '4', 'stereo' => '1', 'label' => ''],
                ['kind' => 'input', 'quantity' => '', 'stereo' => '', 'label' => ''],
            ],
            cards: [['name' => 'Dante card', 'protocol_id' => 'dante', 'inputs' => '64', 'outputs' => '64', 'notes' => '']],
        ));

        $this->assertSame([], $errors);
        $this->assertNotNull($console);
        $this->assertSame(MixingConsoleType::Digital, $console->type);
        $this->assertSame(0, $console->micInputs);
        $this->assertSame(16, $console->localInputs);
        $this->assertSame('Option slot', $console->expansionSlotType);
        $this->assertCount(1, $console->interfaces);
        $this->assertSame(2, $console->interfaces[0]->quantity);
        $this->assertCount(3, $console->channels);
        $this->assertSame('Mix Bus', $console->channels[1]->manufacturerLabel);
        $this->assertTrue($console->channels[2]->isStereo);
        $this->assertNull($console->channels[2]->manufacturerLabel);
        $this->assertSame(40, $console->channelCount(MixingConsoleChannelKind::Input));
        $this->assertSame(['aes50', 'dante'], $console->protocolIds());
        $this->assertSame('64 in / 64 out', $console->cards[0]->channelSummary());
    }

    public function testRejectsInvalidRows(): void
    {
        [$errors] = $this->builder->build('a1', new MixingConsoleInput(
            'digital',
            interfaces: [['protocol_id' => 'aes50', 'quantity' => '1'], ['protocol_id' => 'aes50', 'quantity' => '1']],
            channels: [['kind' => 'input', 'quantity' => '0', 'label' => 'Ch']],
            cards: [['name' => '', 'protocol_id' => 'dante', 'inputs' => '8']],
        ));

        $this->assertArrayHasKey('mc_interfaces', $errors);
        $this->assertArrayHasKey('mc_channels', $errors);
        $this->assertArrayHasKey('mc_cards', $errors);
    }

    public function testCardsNeedExpansionSlots(): void
    {
        [$errors] = $this->builder->build('a1', new MixingConsoleInput('digital', cards: [['name' => 'Dante card', 'protocol_id' => 'dante']]));

        $this->assertArrayHasKey('mc_expansion_slots', $errors);
    }

    public function testStageboxesOfSameManufacturerCanBeNative(): void
    {
        [$errors, $console] = $this->builder->build('a1', new MixingConsoleInput(
            'digital',
            expansionSlots: '1',
            interfaces: [['protocol_id' => 'aes50', 'quantity' => '2']],
            cards: [['name' => 'Dante card', 'protocol_id' => 'dante', 'inputs' => '32', 'outputs' => '32']],
            stageboxes: ['sb-own' => 'native', 'sb-other' => 'limited', 'sb-dante' => 'limited', 'sb-madi' => ''],
        ), 'm-console');

        $this->assertSame([], $errors);
        $this->assertNotNull($console);
        $this->assertSame(['sb-own'], $console->stageboxIds(StageboxCompatibility::Native));
        $this->assertSame(['sb-other', 'sb-dante'], $console->stageboxIds(StageboxCompatibility::Limited));
    }

    public function testStageboxOfOtherManufacturerCannotBeNative(): void
    {
        [$errors, $console] = $this->builder->build('a1', new MixingConsoleInput(
            'digital',
            interfaces: [['protocol_id' => 'aes50', 'quantity' => '1']],
            stageboxes: ['sb-other' => 'native'],
        ), 'm-console');

        $this->assertNull($console);
        $this->assertStringContainsString('kompatibel mit Einschränkungen', $errors['mc_stageboxes']);
    }

    public function testStageboxNeedsProtocolOfferedByConsole(): void
    {
        [$errors, $console] = $this->builder->build('a1', new MixingConsoleInput(
            'digital',
            interfaces: [['protocol_id' => 'aes50', 'quantity' => '1']],
            stageboxes: ['sb-madi' => 'native'],
        ), 'm-console');

        $this->assertNull($console);
        $this->assertStringContainsString('MADI', $errors['mc_stageboxes']);
    }
}
