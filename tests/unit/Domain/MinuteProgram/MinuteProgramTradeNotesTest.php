<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\MinuteProgram;

use App\Domain\MinuteProgram\MinuteProgramTradeNotes;
use Codeception\Test\Unit;

final class MinuteProgramTradeNotesTest extends Unit
{
    public function testNormalizeTrimsAndDropsEmptyNotes(): void
    {
        $this->assertSame(['a' => 'HH 1'], MinuteProgramTradeNotes::normalize(['a' => '  HH 1 ', 'b' => '   ', 'c' => ['x']]));
    }

    public function testMergeKeepsNotesOfTradesThatWereNotSubmitted(): void
    {
        $merged = MinuteProgramTradeNotes::merge(['a' => 'alt', 'b' => 'bleibt', 'c' => 'weg'], ['a' => 'neu', 'c' => '']);

        $this->assertSame(['b' => 'bleibt', 'a' => 'neu'], $merged);
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $json = MinuteProgramTradeNotes::encode(['t1' => 'Bühne frei "jetzt"']);
        $this->assertNotNull($json);
        $this->assertSame(['t1' => 'Bühne frei "jetzt"'], MinuteProgramTradeNotes::decode($json));
        $this->assertNull(MinuteProgramTradeNotes::encode([]));
        $this->assertSame([], MinuteProgramTradeNotes::decode('kein json'));
    }

    public function testTradeIds(): void
    {
        $this->assertNull(MinuteProgramTradeNotes::encodeIds(null));
        $this->assertNull(MinuteProgramTradeNotes::decodeIds(null));
        $this->assertSame('a,b', MinuteProgramTradeNotes::encodeIds(['a', ' b', 'a', '']));
        $this->assertSame([], MinuteProgramTradeNotes::decodeIds(''));
        $this->assertSame(['a', 'b'], MinuteProgramTradeNotes::decodeIds('a,b'));
    }
}
