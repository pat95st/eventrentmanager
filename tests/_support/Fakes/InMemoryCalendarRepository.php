<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\CalendarKind;
use App\Domain\Calendar\CalendarRepositoryInterface;

/**
 * In-Memory-Test-Double für CalendarRepositoryInterface — hält Application-
 * Handler-Unit-Tests unabhängig von einer echten MariaDB-Verbindung (siehe
 * tests/README.md). Bildet nur das nach, was die Handler tatsächlich
 * brauchen (inkl. der in CalendarRepositoryInterface::findAll()
 * dokumentierten Sortierung: Event-Kalender zuerst).
 */
final class InMemoryCalendarRepository implements CalendarRepositoryInterface
{
    /** @var array<string, Calendar> */
    private array $calendars = [];

    public function save(Calendar $calendar): void
    {
        $this->calendars[$calendar->id] = $calendar;
    }

    public function findById(string $id): ?Calendar
    {
        return $this->calendars[$id] ?? null;
    }

    public function findAll(): array
    {
        $calendars = array_values($this->calendars);

        usort($calendars, static function (Calendar $a, Calendar $b): int {
            if ($a->kind !== $b->kind) {
                return $a->kind === CalendarKind::Event ? -1 : 1;
            }

            return $a->name <=> $b->name;
        });

        return $calendars;
    }

    public function delete(string $id): void
    {
        unset($this->calendars[$id]);
    }

    public function count(): int
    {
        return count($this->calendars);
    }
}
