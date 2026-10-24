<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Query\QueryBus;
use App\Application\Rental\Query\ListRentalsInRange;
use App\Infrastructure\Http\MonthGridBuilder;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\View;

final class CalendarController
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {
    }

    /** Monatsansicht: zeigt pro Tag, welches Material unterwegs ist. */
    public function index(Request $request): void
    {
        $reference = MonthGridBuilder::parseMonth($request->query('monat'));
        [$firstOfMonth, , $gridStart, $gridEnd] = MonthGridBuilder::bounds($reference);

        $entries = $this->queries->ask(new ListRentalsInRange(
            $gridStart->format('Y-m-d'),
            $gridEnd->format('Y-m-d'),
        ));

        // Für jeden Tag im Gitter die Veranstaltungen sammeln, die an diesem Tag aktiv sind.
        $entriesByDay = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $entriesByDay[$cursor->format('Y-m-d')] = [];
            $cursor = $cursor->modify('+1 day');
        }
        foreach ($entries as $entry) {
            $day = $entry->rental->startDate->setTime(0, 0);
            $end = $entry->rental->endDate->setTime(0, 0);
            while ($day <= $end) {
                $key = $day->format('Y-m-d');
                if (array_key_exists($key, $entriesByDay)) {
                    $entriesByDay[$key][] = $entry;
                }
                $day = $day->modify('+1 day');
            }
        }

        $weeks = MonthGridBuilder::weeks($gridStart, $gridEnd);

        echo View::page('calendar/index', [
            'weeks' => $weeks,
            'entriesByDay' => $entriesByDay,
            'currentMonth' => $firstOfMonth,
            'prevMonth' => $firstOfMonth->modify('-1 month')->format('Y-m'),
            'nextMonth' => $firstOfMonth->modify('+1 month')->format('Y-m'),
        ], activeNav: 'calendar', title: 'Kalender');
    }
}
