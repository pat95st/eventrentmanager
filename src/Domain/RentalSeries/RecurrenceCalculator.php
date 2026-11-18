<?php

declare(strict_types=1);

namespace App\Domain\RentalSeries;

use DateTimeImmutable;

/**
 * Calculates the start dates of a rental series from its rule. The anchor
 * (first occurrence) is always part of the sequence, even when it does not
 * match the rule itself (e.g. anchor on the 15th, rule "1st Friday").
 */
final class RecurrenceCalculator
{
    /**
     * Full sequence of start dates (anchor first, ascending), limited by the
     * rule's end condition, by $hardEnd (inclusive) and by $max entries.
     *
     * @return DateTimeImmutable[] dates at 00:00
     */
    public function sequence(RecurrenceRule $rule, DateTimeImmutable $anchor, DateTimeImmutable $hardEnd, int $max = PHP_INT_MAX): array
    {
        $anchor = $anchor->setTime(0, 0);
        $hardEnd = $hardEnd->setTime(0, 0);
        if ($rule->endsOn !== null && $rule->endsOn->setTime(0, 0) < $hardEnd) {
            $hardEnd = $rule->endsOn->setTime(0, 0);
        }
        $limit = min($max, $rule->occurrenceCount ?? PHP_INT_MAX);
        if ($limit < 1) {
            return [];
        }

        $dates = [$anchor];
        foreach ($this->candidates($rule, $anchor) as $candidate) {
            if (count($dates) >= $limit || $candidate > $hardEnd) {
                break;
            }
            if ($candidate > $anchor) {
                $dates[] = $candidate;
            }
        }

        return $dates;
    }

    /**
     * Start dates strictly after $after (e.g. the last existing occurrence),
     * at most $max, at most MAX_YEARS after $after.
     *
     * @return DateTimeImmutable[]
     */
    public function datesAfter(RecurrenceRule $rule, DateTimeImmutable $anchor, DateTimeImmutable $after, int $max = RecurrenceRule::MAX_OCCURRENCES): array
    {
        $after = $after->setTime(0, 0);
        $hardEnd = $after->modify('+' . RecurrenceRule::MAX_YEARS . ' years');
        $result = [];
        foreach ($this->sequence($rule, $anchor, $hardEnd) as $date) {
            if ($date > $after) {
                $result[] = $date;
                if (count($result) >= $max) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Endless (ascending) stream of candidate dates; the caller stops.
     *
     * @return \Generator<DateTimeImmutable>
     */
    private function candidates(RecurrenceRule $rule, DateTimeImmutable $anchor): \Generator
    {
        $n = max(1, $rule->interval);
        switch ($rule->type) {
            case RecurrenceType::Weekly:
                $monday = $anchor->modify('-' . ((int) $anchor->format('N') - 1) . ' days');
                for ($week = 0;; $week += $n) {
                    $weekStart = $monday->modify("+{$week} weeks");
                    foreach ($rule->weekdays as $day) {
                        yield $weekStart->modify('+' . ($day - 1) . ' days');
                    }
                }
                // no break: endless loop
            case RecurrenceType::MonthlyDay:
                $firstOfMonth = $anchor->modify('first day of this month');
                for ($month = 0;; $month += $n) {
                    $monthStart = $firstOfMonth->modify("+{$month} months");
                    $day = min($rule->monthDay ?? (int) $anchor->format('j'), (int) $monthStart->format('t'));
                    yield $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('n'), $day);
                }
                // no break: endless loop
            case RecurrenceType::MonthlyWeekday:
                $firstOfMonth = $anchor->modify('first day of this month');
                for ($month = 0;; $month += $n) {
                    yield $this->nthWeekday($firstOfMonth->modify("+{$month} months"), $rule->weekOfMonth ?? 1, $rule->weekday ?? 1);
                }
                // no break: endless loop
            case RecurrenceType::Yearly:
                $month = (int) $anchor->format('n');
                $day = (int) $anchor->format('j');
                for ($year = (int) $anchor->format('Y');; $year += $n) {
                    $first = $anchor->setDate($year, $month, 1);
                    yield $anchor->setDate($year, $month, min($day, (int) $first->format('t')));
                }
                // no break: endless loop
            case RecurrenceType::Manual:
                foreach ($rule->manualDates as $date) {
                    yield new DateTimeImmutable($date . ' 00:00:00');
                }

                return;
        }
    }

    private function nthWeekday(DateTimeImmutable $firstOfMonth, int $weekOfMonth, int $weekday): DateTimeImmutable
    {
        if ($weekOfMonth === -1) {
            $last = $firstOfMonth->modify('last day of this month');
            $back = ((int) $last->format('N') - $weekday + 7) % 7;

            return $last->modify("-{$back} days");
        }
        $forward = ($weekday - (int) $firstOfMonth->format('N') + 7) % 7;

        return $firstOfMonth->modify('+' . ($forward + 7 * ($weekOfMonth - 1)) . ' days');
    }
}
