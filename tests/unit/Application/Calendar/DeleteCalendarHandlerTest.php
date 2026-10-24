<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Calendar;

use App\Application\Calendar\Command\DeleteCalendar;
use App\Application\Calendar\Command\DeleteCalendarHandler;
use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\CalendarKind;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryCalendarRepository;

final class DeleteCalendarHandlerTest extends Unit
{
    private InMemoryCalendarRepository $repository;
    private DeleteCalendarHandler $handler;

    protected function _before(): void
    {
        $this->repository = new InMemoryCalendarRepository();
        $this->handler = new DeleteCalendarHandler($this->repository);
    }

    public function testDeletesARentalCalendar(): void
    {
        $calendar = $this->calendar('rental-1', CalendarKind::Rental);
        $this->repository->save($calendar);

        $this->handler->handle(new DeleteCalendar('rental-1'));

        $this->assertNull($this->repository->findById('rental-1'));
    }

    public function testRefusesToDeleteTheBuiltInEventCalendar(): void
    {
        $calendar = $this->calendar('event-1', CalendarKind::Event);
        $this->repository->save($calendar);

        try {
            $this->handler->handle(new DeleteCalendar('event-1'));
            $this->fail('Erwartete ValidationException wurde nicht geworfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('id', $e->errors());
        }

        // Der Veranstaltungskalender muss trotz des Löschversuchs
        // unverändert erhalten bleiben.
        $this->assertNotNull($this->repository->findById('event-1'));
    }

    public function testThrowsNotFoundForAnUnknownCalendar(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler->handle(new DeleteCalendar('unbekannt'));
    }

    private function calendar(string $id, CalendarKind $kind): Calendar
    {
        $now = new DateTimeImmutable();

        return new Calendar(
            id: $id,
            kind: $kind,
            name: 'Test-Kalender',
            enabled: true,
            categoryId: null,
            manufacturerId: null,
            warehouseId: $kind === CalendarKind::Rental ? 'warehouse-1' : null,
            venueId: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
