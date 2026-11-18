<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\RentalSeries;

use App\Domain\RentalSeries\RecurrenceCalculator;
use App\Domain\RentalSeries\RecurrenceRule;
use App\Domain\RentalSeries\RecurrenceType;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class RecurrenceCalculatorTest extends Unit
{
    private RecurrenceCalculator $calculator;

    protected function _before(): void
    {
        $this->calculator = new RecurrenceCalculator();
    }

    /** @param DateTimeImmutable[] $dates @return string[] */
    private static function ymd(array $dates): array
    {
        return array_map(static fn (DateTimeImmutable $d) => $d->format('Y-m-d'), $dates);
    }

    private function sequence(RecurrenceRule $rule, string $anchor, string $hardEnd = '2030-12-31'): array
    {
        return self::ymd($this->calculator->sequence($rule, new DateTimeImmutable($anchor), new DateTimeImmutable($hardEnd)));
    }

    public function testEverySecondSaturdayWithCount(): void
    {
        $rule = new RecurrenceRule(RecurrenceType::Weekly, 2, [6], occurrenceCount: 4);

        $this->assertSame(['2027-01-02', '2027-01-16', '2027-01-30', '2027-02-13'], $this->sequence($rule, '2027-01-02'));
    }

    public function testWeeklyOnSeveralWeekdaysStartsAfterAnchorInSameWeek(): void
    {
        // Anchor Wednesday; Monday of that week lies before the anchor and is skipped.
        $rule = new RecurrenceRule(RecurrenceType::Weekly, 1, [1, 3, 5], endsOn: new DateTimeImmutable('2027-01-11'));

        $this->assertSame(['2027-01-06', '2027-01-08', '2027-01-11'], $this->sequence($rule, '2027-01-06'));
    }

    public function testMonthlyOn31stUsesLastDayOfShortMonths(): void
    {
        $rule = new RecurrenceRule(RecurrenceType::MonthlyDay, 1, monthDay: 31, occurrenceCount: 4);

        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'], $this->sequence($rule, '2027-01-31'));
    }

    public function testFirstFridayEveryMonthWithAnchorNotMatchingTheRule(): void
    {
        // Anchor 15.01. is kept as first occurrence, then each 1st Friday.
        $rule = new RecurrenceRule(RecurrenceType::MonthlyWeekday, 1, weekOfMonth: 1, weekday: 5, occurrenceCount: 3);

        $this->assertSame(['2027-01-15', '2027-02-05', '2027-03-05'], $this->sequence($rule, '2027-01-15'));
    }

    public function testLastSundayOfMonth(): void
    {
        $rule = new RecurrenceRule(RecurrenceType::MonthlyWeekday, 1, weekOfMonth: -1, weekday: 7, occurrenceCount: 3);

        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-28'], $this->sequence($rule, '2027-01-31'));
    }

    public function testEveryThreeMonths(): void
    {
        $rule = new RecurrenceRule(RecurrenceType::MonthlyDay, 3, monthDay: 10, occurrenceCount: 3);

        $this->assertSame(['2027-01-10', '2027-04-10', '2027-07-10'], $this->sequence($rule, '2027-01-10'));
    }

    public function testYearlyOnLeapDayFallsBackToFebruary28th(): void
    {
        $rule = new RecurrenceRule(RecurrenceType::Yearly, 1, occurrenceCount: 3);

        $this->assertSame(['2028-02-29', '2029-02-28', '2030-02-28'], $this->sequence($rule, '2028-02-29'));
    }

    public function testManualDatesAreUsedAsGiven(): void
    {
        $rule = new RecurrenceRule(RecurrenceType::Manual, manualDates: ['2027-03-01', '2027-05-17']);

        $this->assertSame(['2027-01-10', '2027-03-01', '2027-05-17'], $this->sequence($rule, '2027-01-10'));
    }

    public function testHardEndLimitsOpenEndedRule(): void
    {
        $rule = new RecurrenceRule(RecurrenceType::Weekly, 1, [1]);

        $this->assertSame(['2027-01-04', '2027-01-11', '2027-01-18'], $this->sequence($rule, '2027-01-04', '2027-01-20'));
    }

    public function testDatesAfterLastOccurrence(): void
    {
        $rule = new RecurrenceRule(RecurrenceType::Weekly, 2, [6]);
        $dates = $this->calculator->datesAfter($rule, new DateTimeImmutable('2027-01-02'), new DateTimeImmutable('2027-01-30'), 2);

        // Same rhythm as from the anchor: 13.02., 27.02. (not "two weeks after any date")
        $this->assertSame(['2027-02-13', '2027-02-27'], self::ymd($dates));
    }
}
