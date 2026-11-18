<?php

declare(strict_types=1);

namespace App\Domain\RentalSeries;

use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Rhythm and end condition of a rental series (migration 176). Immutable;
 * create via fromInput() (form values) or the constructor (persistence).
 */
final class RecurrenceRule
{
    /** Upper bound for the number of occurrences generated in one go. */
    public const MAX_OCCURRENCES = 100;

    /** Upper bound (in years from the first new occurrence) for generation. */
    public const MAX_YEARS = 2;

    /**
     * @param int[] $weekdays ISO weekdays 1 (Mon) .. 7 (Sun), sorted, for Weekly
     * @param string[] $manualDates sorted 'Y-m-d' dates for Manual
     */
    public function __construct(
        public readonly RecurrenceType $type,
        public readonly int $interval = 1,
        public readonly array $weekdays = [],
        public readonly ?int $monthDay = null,
        public readonly ?int $weekOfMonth = null, // 1..4, -1 = last
        public readonly ?int $weekday = null,     // ISO 1..7
        public readonly array $manualDates = [],
        public readonly ?DateTimeImmutable $endsOn = null,
        public readonly ?int $occurrenceCount = null,
    ) {
    }

    /**
     * Builds a rule from form values. Missing details (weekday, day of
     * month, ...) default to the anchor date, i.e. the first occurrence.
     *
     * Keys: type, interval, weekdays (string[]), month_day, week_of_month,
     * weekday, manual_dates (text), end_mode ('date'|'count'), ends_on,
     * occurrence_count.
     *
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    public static function fromInput(array $input, DateTimeImmutable $anchor): self
    {
        $errors = [];
        $type = RecurrenceType::tryFrom(self::str($input, 'type'));
        if ($type === null) {
            throw new ValidationException(['type' => 'Bitte einen Rhythmus auswählen.']);
        }

        $interval = 1;
        if ($type !== RecurrenceType::Manual) {
            $rawInterval = self::str($input, 'interval');
            $interval = $rawInterval === '' ? 1 : (int) filter_var($rawInterval, FILTER_VALIDATE_INT);
            if ($interval < 1 || $interval > 52) {
                $errors['interval'] = 'Das Intervall muss zwischen 1 und 52 liegen.';
                $interval = 1;
            }
        }

        $weekdays = [];
        $monthDay = null;
        $weekOfMonth = null;
        $weekday = null;
        $manualDates = [];

        switch ($type) {
            case RecurrenceType::Weekly:
                $raw = is_array($input['weekdays'] ?? null) ? $input['weekdays'] : [];
                foreach ($raw as $value) {
                    $day = (int) $value;
                    if ($day >= 1 && $day <= 7) {
                        $weekdays[$day] = $day;
                    }
                }
                if ($weekdays === []) {
                    $weekdays[(int) $anchor->format('N')] = (int) $anchor->format('N');
                }
                ksort($weekdays);
                $weekdays = array_values($weekdays);
                break;
            case RecurrenceType::MonthlyDay:
                $rawDay = self::str($input, 'month_day');
                $monthDay = $rawDay === '' ? (int) $anchor->format('j') : (int) $rawDay;
                if ($monthDay < 1 || $monthDay > 31) {
                    $errors['month_day'] = 'Bitte einen Tag zwischen 1 und 31 angeben.';
                }
                break;
            case RecurrenceType::MonthlyWeekday:
                $rawWeek = self::str($input, 'week_of_month');
                $weekOfMonth = $rawWeek === '' ? min(4, intdiv((int) $anchor->format('j') - 1, 7) + 1) : (int) $rawWeek;
                if (!in_array($weekOfMonth, [1, 2, 3, 4, -1], true)) {
                    $errors['week_of_month'] = 'Bitte „1.“ bis „4.“ oder „letzter“ auswählen.';
                }
                $rawWeekday = self::str($input, 'weekday');
                $weekday = $rawWeekday === '' ? (int) $anchor->format('N') : (int) $rawWeekday;
                if ($weekday < 1 || $weekday > 7) {
                    $errors['weekday'] = 'Bitte einen Wochentag auswählen.';
                }
                break;
            case RecurrenceType::Yearly:
                break;
            case RecurrenceType::Manual:
                $tokens = preg_split('/[\s,;]+/', self::str($input, 'manual_dates')) ?: [];
                foreach ($tokens as $token) {
                    if ($token === '') {
                        continue;
                    }
                    $date = self::parseDate($token);
                    if ($date === null) {
                        $errors['manual_dates'] = "„{$token}“ ist kein gültiges Datum (TT.MM.JJJJ).";
                        continue;
                    }
                    if ($date > $anchor) {
                        $manualDates[$date->format('Y-m-d')] = $date->format('Y-m-d');
                    }
                }
                ksort($manualDates);
                $manualDates = array_values($manualDates);
                if ($manualDates === [] && !isset($errors['manual_dates'])) {
                    $errors['manual_dates'] = 'Bitte mindestens ein Datum nach dem ersten Termin angeben.';
                }
                break;
        }

        $endsOn = null;
        $occurrenceCount = null;
        if ($type !== RecurrenceType::Manual) {
            if (self::str($input, 'end_mode') === 'count') {
                $count = filter_var(self::str($input, 'occurrence_count'), FILTER_VALIDATE_INT);
                if ($count === false || $count < 2 || $count > self::MAX_OCCURRENCES) {
                    $errors['occurrence_count'] = 'Die Anzahl der Termine muss zwischen 2 und ' . self::MAX_OCCURRENCES . ' liegen.';
                } else {
                    $occurrenceCount = $count;
                }
            } else {
                $endsOn = self::parseDate(self::str($input, 'ends_on'));
                if ($endsOn === null) {
                    $errors['ends_on'] = 'Bitte ein Enddatum oder eine Anzahl Termine angeben.';
                } elseif ($endsOn <= $anchor) {
                    $errors['ends_on'] = 'Das Enddatum muss nach dem ersten Termin liegen.';
                } elseif ($endsOn > $anchor->modify('+' . self::MAX_YEARS . ' years')) {
                    $errors['ends_on'] = 'Eine Serie kann höchstens ' . self::MAX_YEARS . ' Jahre im Voraus angelegt werden; später über „Weitere Termine erzeugen“ verlängern.';
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($type, $interval, $weekdays, $monthDay, $weekOfMonth, $weekday, $manualDates, $endsOn, $occurrenceCount);
    }

    public function withEnd(?DateTimeImmutable $endsOn, ?int $occurrenceCount): self
    {
        return new self($this->type, $this->interval, $this->weekdays, $this->monthDay, $this->weekOfMonth, $this->weekday, $this->manualDates, $endsOn, $occurrenceCount);
    }

    /** @param string[] $manualDates */
    public function withManualDates(array $manualDates): self
    {
        sort($manualDates);

        return new self($this->type, $this->interval, $this->weekdays, $this->monthDay, $this->weekOfMonth, $this->weekday, array_values(array_unique($manualDates)), $this->endsOn, $this->occurrenceCount);
    }

    /** Human readable description, e.g. "alle 2 Wochen (Sa), bis 31.12.2027". */
    public function describe(): string
    {
        $dayNames = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $longDayNames = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
        $every = static fn (int $n, string $one, string $many): string => $n === 1 ? $one : "alle {$n} {$many}";

        $text = match ($this->type) {
            RecurrenceType::Weekly => $every($this->interval, 'wöchentlich', 'Wochen')
                . ' (' . implode(', ', array_map(static fn (int $d) => $dayNames[$d], $this->weekdays)) . ')',
            RecurrenceType::MonthlyDay => $every($this->interval, 'monatlich', 'Monate') . ' am ' . $this->monthDay . '.',
            RecurrenceType::MonthlyWeekday => $every($this->interval, 'monatlich', 'Monate') . ' am '
                . ($this->weekOfMonth === -1 ? 'letzten' : $this->weekOfMonth . '.') . ' '
                . ($longDayNames[$this->weekday ?? 1]),
            RecurrenceType::Yearly => $every($this->interval, 'jährlich', 'Jahre'),
            RecurrenceType::Manual => 'freie Terminliste (' . count($this->manualDates) . ' weitere Termine)',
        };

        if ($this->endsOn !== null) {
            $text .= ', bis ' . $this->endsOn->format('d.m.Y');
        } elseif ($this->occurrenceCount !== null) {
            $text .= ', ' . $this->occurrenceCount . ' Termine';
        }

        return $text;
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd.m.Y', 'j.n.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $input */
    private static function str(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
