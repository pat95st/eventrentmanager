<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Query\QueryBus;
use App\Application\Rental\Query\CalendarRentalEntry;
use App\Application\Rental\Query\ListRentalsInRange;
use App\Infrastructure\Http\MonthGridBuilder;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\View;
use DateTimeImmutable;

/**
 * Veranstaltungskalender mit mehreren Ansichten (Nutzerwunsch):
 *  - monat: Monatsraster (bisherige Ansicht)
 *  - woche: Tage nebeneinander, Stunden von oben nach unten; Zeitraum aus
 *    Abholung/Rückgabe, sonst ganztägig
 *  - liste: kommende Veranstaltungen nach Tagen (Standard auf Mobilgeräten)
 *  - jahr: 12 Mini-Monate, Veranstaltungstage als Punkte bzw. Linien bei
 *    mehrtägigen Veranstaltungen (ähnlich der iOS-Jahresansicht)
 */
final class CalendarController
{
    public const VIEWS = ['woche' => 'Woche', 'monat' => 'Monat', 'jahr' => 'Jahr', 'liste' => 'Liste'];

    /** Farbpalette für Veranstaltungen (stabil je Veranstaltung über die ID). */
    private const COLORS = ['#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d'];

    public function __construct(
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $view = array_key_exists((string) $request->query('ansicht'), self::VIEWS) ? (string) $request->query('ansicht') : 'monat';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query('datum'));
        $anchor = $date !== false ? $date : new DateTimeImmutable('today');
        // Rückwärtskompatibel: ?monat=YYYY-MM
        if ($request->query('monat') !== null && $date === false) {
            $anchor = MonthGridBuilder::parseMonth($request->query('monat'));
        }

        match ($view) {
            'woche' => $this->week($anchor),
            'liste' => $this->agenda($anchor),
            'jahr' => $this->year($anchor),
            default => $this->month($anchor),
        };
    }

    public static function colorFor(string $rentalId): string
    {
        return self::COLORS[hexdec(substr(md5($rentalId), 0, 6)) % count(self::COLORS)];
    }

    private function month(DateTimeImmutable $anchor): void
    {
        [$firstOfMonth, , $gridStart, $gridEnd] = MonthGridBuilder::bounds($anchor);
        $entries = $this->entries($gridStart, $gridEnd);

        echo View::page('calendar/index', [
            'view' => 'monat',
            'anchor' => $firstOfMonth,
            'weeks' => MonthGridBuilder::weeks($gridStart, $gridEnd),
            'entriesByDay' => $this->byDay($entries, $gridStart, $gridEnd),
            'currentMonth' => $firstOfMonth,
            'prevDate' => $firstOfMonth->modify('-1 month')->format('Y-m-d'),
            'nextDate' => $firstOfMonth->modify('+1 month')->format('Y-m-d'),
            'label' => self::monthName($firstOfMonth) . ' ' . $firstOfMonth->format('Y'),
        ], activeNav: 'calendar', title: 'Kalender');
    }

    private function week(DateTimeImmutable $anchor): void
    {
        $monday = $anchor->modify('monday this week')->setTime(0, 0);
        $sunday = $monday->modify('+6 days');
        $entries = $this->entries($monday, $sunday);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $monday->modify("+{$i} days");
            $days[$day->format('Y-m-d')] = ['date' => $day, 'allDay' => [], 'timed' => []];
        }
        foreach ($entries as $entry) {
            $r = $entry->rental;
            $timed = $r->pickupAt !== null && $r->returnAt !== null && $r->returnAt > $r->pickupAt;
            $from = $timed ? $r->pickupAt : $r->startDate->setTime(0, 0);
            $to = $timed ? $r->returnAt : $r->endDate->setTime(23, 59);
            foreach ($days as $key => &$slot) {
                $dayStart = $slot['date'];
                $dayEnd = $dayStart->setTime(23, 59, 59);
                if ($from > $dayEnd || $to < $dayStart) {
                    continue;
                }
                if (!$timed) {
                    $slot['allDay'][] = $entry;
                    continue;
                }
                $startMin = $from > $dayStart ? (int) $from->format('G') * 60 + (int) $from->format('i') : 0;
                $endMin = $to < $dayEnd ? (int) $to->format('G') * 60 + (int) $to->format('i') : 24 * 60;
                $slot['timed'][] = ['entry' => $entry, 'start' => $startMin, 'end' => max($endMin, $startMin + 30), 'continuesBefore' => $from < $dayStart, 'continuesAfter' => $to > $dayEnd];
            }
            unset($slot);
        }
        // Überlappende Termine nebeneinander (Spalten je Tag, gierig).
        foreach ($days as &$slot) {
            usort($slot['timed'], static fn ($a, $b) => [$a['start'], -$a['end']] <=> [$b['start'], -$b['end']]);
            $columnsEnd = [];
            foreach ($slot['timed'] as &$item) {
                $col = 0;
                while (isset($columnsEnd[$col]) && $columnsEnd[$col] > $item['start']) {
                    $col++;
                }
                $columnsEnd[$col] = $item['end'];
                $item['column'] = $col;
            }
            unset($item);
            $slot['columns'] = max(1, count($columnsEnd));
        }
        unset($slot);

        $label = $monday->format('d.m.') . ' – ' . $sunday->format('d.m.Y') . ' (KW ' . $monday->format('W') . ')';
        echo View::page('calendar/week', [
            'view' => 'woche',
            'anchor' => $monday,
            'days' => $days,
            'prevDate' => $monday->modify('-7 days')->format('Y-m-d'),
            'nextDate' => $monday->modify('+7 days')->format('Y-m-d'),
            'label' => $label,
        ], activeNav: 'calendar', title: 'Kalender – Woche');
    }

    private function agenda(DateTimeImmutable $anchor): void
    {
        $from = $anchor->setTime(0, 0);
        $to = $from->modify('+60 days');
        $entries = $this->entries($from, $to);
        usort($entries, static fn (CalendarRentalEntry $a, CalendarRentalEntry $b) => [self::startOf($a), $a->rental->eventName] <=> [self::startOf($b), $b->rental->eventName]);
        // Nach erstem sichtbaren Tag gruppieren (laufende Veranstaltungen unter dem Starttag des Zeitraums).
        $groups = [];
        foreach ($entries as $entry) {
            $first = max($entry->rental->startDate->setTime(0, 0), $from);
            $groups[$first->format('Y-m-d')][] = $entry;
        }
        ksort($groups);

        echo View::page('calendar/agenda', [
            'view' => 'liste',
            'anchor' => $from,
            'groups' => $groups,
            'prevDate' => $from->modify('-30 days')->format('Y-m-d'),
            'nextDate' => $from->modify('+30 days')->format('Y-m-d'),
            'label' => 'ab ' . $from->format('d.m.Y'),
        ], activeNav: 'calendar', title: 'Kalender – Liste');
    }

    private function year(DateTimeImmutable $anchor): void
    {
        $year = (int) $anchor->format('Y');
        $start = new DateTimeImmutable($year . '-01-01');
        $end = new DateTimeImmutable($year . '-12-31');
        $entries = $this->entries($start, $end);
        usort($entries, static fn ($a, $b) => [$a->rental->startDate, $b->rental->endDate] <=> [$b->rental->startDate, $a->rental->endDate]);

        // Spur (Zeile) je Veranstaltung, damit Linien mehrtägiger
        // Veranstaltungen über die Tage hinweg auf gleicher Höhe bleiben.
        $laneEnds = [];
        $marks = []; // 'Y-m-d' => list<array{lane:int, color:string, kind:string, title:string}>
        foreach ($entries as $entry) {
            $r = $entry->rental;
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] >= $r->startDate->format('Y-m-d')) {
                $lane++;
            }
            $laneEnds[$lane] = $r->endDate->format('Y-m-d');
            $single = $r->startDate->format('Y-m-d') === $r->endDate->format('Y-m-d');
            for ($day = $r->startDate->setTime(0, 0); $day <= $r->endDate; $day = $day->modify('+1 day')) {
                $key = $day->format('Y-m-d');
                $kind = $single ? 'dot' : ($key === $r->startDate->format('Y-m-d') ? 'start' : ($key === $r->endDate->format('Y-m-d') ? 'end' : 'mid'));
                $marks[$key][] = ['lane' => $lane, 'color' => self::colorFor($r->id), 'kind' => $kind, 'title' => $r->eventName];
            }
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $first = new DateTimeImmutable(sprintf('%d-%02d-01', $year, $m));
            [, , $gridStart, $gridEnd] = MonthGridBuilder::bounds($first);
            $months[] = ['first' => $first, 'weeks' => MonthGridBuilder::weeks($gridStart, $gridEnd)];
        }

        echo View::page('calendar/year', [
            'view' => 'jahr',
            'anchor' => $start,
            'months' => $months,
            'marks' => $marks,
            'eventCount' => count($entries),
            'prevDate' => ($year - 1) . '-01-01',
            'nextDate' => ($year + 1) . '-01-01',
            'label' => (string) $year,
        ], activeNav: 'calendar', title: 'Kalender – ' . $year);
    }

    /** @return CalendarRentalEntry[] */
    private function entries(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->queries->ask(new ListRentalsInRange($from->format('Y-m-d'), $to->format('Y-m-d')));
    }

    /**
     * @param CalendarRentalEntry[] $entries
     * @return array<string, CalendarRentalEntry[]>
     */
    private function byDay(array $entries, DateTimeImmutable $gridStart, DateTimeImmutable $gridEnd): array
    {
        $entriesByDay = [];
        for ($cursor = $gridStart; $cursor <= $gridEnd; $cursor = $cursor->modify('+1 day')) {
            $entriesByDay[$cursor->format('Y-m-d')] = [];
        }
        foreach ($entries as $entry) {
            for ($day = $entry->rental->startDate->setTime(0, 0); $day <= $entry->rental->endDate->setTime(0, 0); $day = $day->modify('+1 day')) {
                $key = $day->format('Y-m-d');
                if (array_key_exists($key, $entriesByDay)) {
                    $entriesByDay[$key][] = $entry;
                }
            }
        }

        return $entriesByDay;
    }

    private static function startOf(CalendarRentalEntry $entry): DateTimeImmutable
    {
        return $entry->rental->pickupAt ?? $entry->rental->startDate;
    }

    public static function monthName(DateTimeImmutable $date): string
    {
        return [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'][(int) $date->format('n')];
    }
}
