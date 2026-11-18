<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\RentalSeries;

use App\Domain\Exception\ValidationException;
use App\Domain\RentalSeries\RecurrenceRule;
use App\Domain\RentalSeries\RecurrenceType;
use App\Domain\RentalSeries\RentalSeries;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class RecurrenceRuleTest extends Unit
{
    private DateTimeImmutable $anchor;

    protected function _before(): void
    {
        $this->anchor = new DateTimeImmutable('2027-01-02'); // Saturday
    }

    public function testWeeklyDefaultsToWeekdayOfFirstOccurrence(): void
    {
        $rule = RecurrenceRule::fromInput(['type' => 'weekly', 'interval' => '2', 'end_mode' => 'count', 'occurrence_count' => '5'], $this->anchor);

        $this->assertSame(RecurrenceType::Weekly, $rule->type);
        $this->assertSame([6], $rule->weekdays);
        $this->assertSame(5, $rule->occurrenceCount);
        $this->assertSame('alle 2 Wochen (Sa), 5 Termine', $rule->describe());
    }

    public function testMonthlyWeekdayDefaultsFromAnchor(): void
    {
        $rule = RecurrenceRule::fromInput(['type' => 'monthly_weekday', 'end_mode' => 'date', 'ends_on' => '2027-06-30'], $this->anchor);

        $this->assertSame(1, $rule->weekOfMonth);
        $this->assertSame(6, $rule->weekday);
        $this->assertSame('2027-06-30', $rule->endsOn?->format('Y-m-d'));
    }

    public function testManualDatesAcceptGermanFormatAndDropDatesBeforeAnchor(): void
    {
        $rule = RecurrenceRule::fromInput(['type' => 'manual', 'manual_dates' => "14.03.2027\n2026-12-01, 2027-02-01"], $this->anchor);

        $this->assertSame(['2027-02-01', '2027-03-14'], $rule->manualDates);
    }

    public function testEndIsRequiredAndLimited(): void
    {
        try {
            RecurrenceRule::fromInput(['type' => 'weekly', 'end_mode' => 'date', 'ends_on' => ''], $this->anchor);
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ends_on', $e->errors());
        }

        try {
            RecurrenceRule::fromInput(['type' => 'weekly', 'end_mode' => 'count', 'occurrence_count' => '500'], $this->anchor);
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('occurrence_count', $e->errors());
        }

        try {
            RecurrenceRule::fromInput(['type' => 'weekly', 'end_mode' => 'date', 'ends_on' => '2030-01-01'], $this->anchor);
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Jahre', $e->errors()['ends_on']);
        }
    }

    public function testInvalidTypeAndInterval(): void
    {
        $this->expectException(ValidationException::class);
        RecurrenceRule::fromInput(['type' => 'daily'], $this->anchor);
    }

    public function testNameTemplatePlaceholders(): void
    {
        $now = new DateTimeImmutable();
        $rule = new RecurrenceRule(RecurrenceType::Yearly);
        $series = new RentalSeries('s', $rule, $this->anchor, 'Stadtfest {jahr} #{nr} ({datum})', null, null, $now, $now);
        $plain = new RentalSeries('s', $rule, $this->anchor, null, null, null, $now, $now);

        $this->assertSame('Stadtfest 2028 #3 (05.06.2028)', $series->nameFor('Basis', new DateTimeImmutable('2028-06-05'), 3));
        $this->assertSame('Basis', $plain->nameFor('Basis', new DateTimeImmutable('2028-06-05'), 3));
    }
}
