<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\MixingConsole;

use App\Domain\MixingConsole\ArticleMixingConsole;
use App\Domain\MixingConsole\MixingConsoleChannel;
use App\Domain\MixingConsole\MixingConsoleChannelKind;
use App\Domain\MixingConsole\MixingConsoleType;
use Codeception\Test\Unit;

final class ArticleMixingConsoleTest extends Unit
{
    public function testAnalogLabels(): void
    {
        $console = new ArticleMixingConsole('a1', MixingConsoleType::Analog, micInputs: 16, stereoInputs: 4, auxPost: 2);

        $this->assertSame('20 (16 × Mikrofon, 4 × Stereo)', $console->analogInputsLabel());
        $this->assertSame('2 × Post', $console->auxLabel());
        $this->assertSame('Analog · 20 Kanäle', $console->summary());
        $this->assertSame('keine', (new ArticleMixingConsole('a2', MixingConsoleType::Analog))->auxLabel());
    }

    public function testDigitalSummaryCountsInputChannels(): void
    {
        $console = new ArticleMixingConsole('a1', MixingConsoleType::Digital, channels: [
            new MixingConsoleChannel(MixingConsoleChannelKind::Input, 32),
            new MixingConsoleChannel(MixingConsoleChannelKind::Input, 8, true, 'Stereo Input'),
            new MixingConsoleChannel(MixingConsoleChannelKind::Aux, 16, false, 'Mix Bus'),
        ]);

        $this->assertSame('Digital · 40 Eingangskanäle', $console->summary());
        $this->assertSame('Mix Bus', $console->channels[2]->displayName());
        $this->assertSame('Eingangskanal', $console->channels[0]->displayName());
    }
}
