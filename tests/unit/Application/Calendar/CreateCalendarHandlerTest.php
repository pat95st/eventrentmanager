<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Calendar;

use App\Application\Calendar\Command\CreateCalendar;
use App\Application\Calendar\Command\CreateCalendarHandler;
use App\Domain\Calendar\CalendarKind;
use App\Domain\Exception\ValidationException;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryCalendarRepository;

final class CreateCalendarHandlerTest extends Unit
{
    private InMemoryCalendarRepository $repository;
    private CreateCalendarHandler $handler;

    protected function _before(): void
    {
        $this->repository = new InMemoryCalendarRepository();
        $this->handler = new CreateCalendarHandler($this->repository);
    }

    public function testCreatesADisabledRentalCalendarWithAtLeastOneFilter(): void
    {
        $id = $this->handler->handle(new CreateCalendar(
            name: 'Lager Nord',
            categoryId: null,
            manufacturerId: null,
            warehouseId: 'warehouse-1',
            venueId: null,
        ));

        $this->assertIsString($id);
        $calendar = $this->repository->findById($id);

        $this->assertNotNull($calendar);
        $this->assertSame(CalendarKind::Rental, $calendar->kind);
        $this->assertSame('Lager Nord', $calendar->name);
        $this->assertSame('warehouse-1', $calendar->warehouseId);
        // Neu angelegte Vermietungskalender starten deaktiviert — ein
        // Administrator muss sie bewusst aktivieren (siehe
        // CreateCalendarHandler).
        $this->assertFalse($calendar->enabled);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateCalendar(
            name: '   ',
            categoryId: null,
            manufacturerId: null,
            warehouseId: 'warehouse-1',
            venueId: null,
        ));
    }

    public function testRejectsACalendarWithoutAnyFilterCriterion(): void
    {
        try {
            $this->handler->handle(new CreateCalendar(
                name: 'Ohne Filter',
                categoryId: null,
                manufacturerId: null,
                warehouseId: null,
                venueId: null,
            ));
            $this->fail('Erwartete ValidationException wurde nicht geworfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('filter', $e->errors());
        }

        $this->assertSame(0, $this->repository->count());
    }

    public function testTreatsEmptyStringFilterValuesAsNotSet(): void
    {
        // Formulare senden für nicht ausgewählte <select>-Felder oft einen
        // leeren String statt null — muss identisch zu null behandelt
        // werden (siehe CreateCalendarHandler::nullIfEmpty()).
        $this->expectException(ValidationException::class);

        $this->handler->handle(new CreateCalendar(
            name: 'Ohne Filter',
            categoryId: '',
            manufacturerId: '',
            warehouseId: '',
            venueId: '',
        ));
    }
}
