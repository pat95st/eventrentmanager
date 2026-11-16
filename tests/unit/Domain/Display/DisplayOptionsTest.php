<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Display;

use App\Domain\Display\DisplayOptions;
use App\Domain\Display\DisplayRole;
use Codeception\Test\Unit;

final class DisplayOptionsTest extends Unit
{
    public function testRoleDefaults(): void
    {
        $stage = DisplayOptions::defaultsFor(DisplayRole::Stage);
        $this->assertSame(['general', 'stage'], $stage->notes);
        $this->assertFalse($stage->allowControl);
        $this->assertFalse($stage->showItemList);

        $foh = DisplayOptions::defaultsFor(DisplayRole::Foh);
        $this->assertSame(['general', 'sound', 'light', 'video', 'stage'], $foh->notes);
        $this->assertTrue($foh->allowControl);
        $this->assertTrue($foh->showItemList);
    }

    public function testFromArraySanitizesAndRoundTrips(): void
    {
        $options = DisplayOptions::fromArray(['notes' => ['stage', 'bogus', 'sound'], 'theme' => 'neon', 'fontScale' => 999, 'showClock' => false]);
        $this->assertSame(['sound', 'stage'], $options->notes, 'canonical order, unknown trades dropped');
        $this->assertSame('dark', $options->theme);
        $this->assertSame(200, $options->fontScale);
        $this->assertFalse($options->showClock);
        $this->assertTrue($options->showDelay, 'missing keys fall back to the role defaults');

        $this->assertEquals($options, DisplayOptions::fromArray($options->toArray()));
    }

    public function testMissingDataUsesRoleDefaults(): void
    {
        $this->assertEquals(DisplayOptions::defaultsFor(DisplayRole::Foh), DisplayOptions::fromArray(null, DisplayRole::Foh));
    }
}
