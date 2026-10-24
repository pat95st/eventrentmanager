<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Calendar;

use App\Application\Calendar\Command\SetCalendarAccess;
use App\Application\Calendar\Command\SetCalendarAccessHandler;
use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\CalendarKind;
use App\Domain\Exception\NotFoundException;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryCalendarAccessRepository;
use Tests\Support\Fakes\InMemoryCalendarRepository;

final class SetCalendarAccessHandlerTest extends Unit
{
    private InMemoryCalendarRepository $calendars;
    private InMemoryCalendarAccessRepository $access;
    private SetCalendarAccessHandler $handler;

    protected function _before(): void
    {
        $this->calendars = new InMemoryCalendarRepository();
        $this->access = new InMemoryCalendarAccessRepository();
        $this->handler = new SetCalendarAccessHandler($this->calendars, $this->access);

        $now = new DateTimeImmutable();
        $this->calendars->save(new Calendar(
            id: 'calendar-1',
            kind: CalendarKind::Rental,
            name: 'Test-Kalender',
            enabled: true,
            categoryId: null,
            manufacturerId: null,
            warehouseId: 'warehouse-1',
            venueId: null,
            createdAt: $now,
            updatedAt: $now,
        ));
    }

    public function testReplacesTheAccessListForAnExistingCalendar(): void
    {
        $this->access->replaceForCalendar('calendar-1', ['user-old']);

        $this->handler->handle(new SetCalendarAccess('calendar-1', ['user-a', 'user-b']));

        $this->assertSame(['user-a', 'user-b'], $this->access->userIdsForCalendar('calendar-1'));
        $this->assertFalse($this->access->hasAccess('calendar-1', 'user-old'));
        $this->assertTrue($this->access->hasAccess('calendar-1', 'user-a'));
    }

    public function testAnEmptyListRevokesAllExplicitAccess(): void
    {
        $this->access->replaceForCalendar('calendar-1', ['user-a']);

        $this->handler->handle(new SetCalendarAccess('calendar-1', []));

        $this->assertSame([], $this->access->userIdsForCalendar('calendar-1'));
    }

    public function testThrowsNotFoundForAnUnknownCalendar(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler->handle(new SetCalendarAccess('unbekannt', ['user-a']));
    }
}
