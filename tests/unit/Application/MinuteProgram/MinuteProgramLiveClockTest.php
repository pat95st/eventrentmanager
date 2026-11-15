<?php

declare(strict_types=1);

namespace Tests\Unit\Application\MinuteProgram;

use App\Application\MinuteProgram\Command\MinuteProgramLiveClock;
use App\Domain\Exception\ValidationException;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class MinuteProgramLiveClockTest extends Unit
{
    public function testParseDelay(): void
    {
        $cases = ['5' => 300, '+5' => 300, '-2' => -120, '1,5' => 90, '4:30' => 270, '-1:15' => -75, '−0:45' => -45, '1:05:00' => 3900, '' => 0, ' +0:05 ' => 5];
        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, MinuteProgramLiveClock::parseDelay((string) $input), 'Input: ' . $input);
        }
    }

    public function testParseDelayRejectsGarbage(): void
    {
        $this->expectException(ValidationException::class);
        MinuteProgramLiveClock::parseDelay('fünf');
    }

    public function testParseDelayRejectsMoreThanADay(): void
    {
        $this->expectException(ValidationException::class);
        MinuteProgramLiveClock::parseDelay('25:00:00');
    }

    public function testParseTimeUsesTheClosestDay(): void
    {
        $reference = new DateTimeImmutable('2026-11-15 23:50:00');

        $this->assertSame('2026-11-15 23:52:10', MinuteProgramLiveClock::parseTime('23:52:10', $reference)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-16 00:10:00', MinuteProgramLiveClock::parseTime('00:10', $reference)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-14 23:40:00', MinuteProgramLiveClock::parseTime('23.40', new DateTimeImmutable('2026-11-15 00:05:00'))->format('Y-m-d H:i:s'));
    }

    public function testParseTimeRejectsInvalidTimes(): void
    {
        $this->expectException(ValidationException::class);
        MinuteProgramLiveClock::parseTime('25:00', new DateTimeImmutable());
    }
}
