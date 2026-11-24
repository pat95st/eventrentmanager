<?php

declare(strict_types=1);

namespace Tests\Unit\Application\PlannedTrip;

use App\Application\Logbook\LogbookDistanceService;
use App\Application\Logbook\LogbookLocationResolver;
use App\Application\PlannedTrip\PlannedTripService;
use App\Application\TravelCost\TravelCostRate;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookLocationType;
use App\Domain\PlannedTrip\PlannedTrip;
use App\Domain\Rental\Rental;
use App\Domain\Routing\PlannedVehicle;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Routing\RoutingSettings;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleSettings;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Domain\Warehouse\Warehouse;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryPlannedTripRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;

/** Planned trips of events that are not tours (migration 186). */
final class PlannedTripServiceTest extends Unit
{
    private InMemoryPlannedTripRepository $trips;
    private InMemoryRentalRepository $rentals;
    private PlannedTripService $service;
    /** @var PlannedVehicle[] */
    private array $planned = [];

    protected function _before(): void
    {
        $this->trips = new InMemoryPlannedTripRepository();
        $this->rentals = new InMemoryRentalRepository();
        $now = new DateTimeImmutable();
        $warehouses = $this->createMock(WarehouseRepositoryInterface::class);
        $warehouses->method('findById')->willReturnCallback(static fn (string $id) => in_array($id, ['wh-1', 'wh-2'], true)
            ? new Warehouse($id, $id === 'wh-1' ? 'Hauptlager' : 'Außenlager', 'Weg 1', '90000', 'Stadt', null, null, null, null, $now, $now)
            : null);
        $resolver = new LogbookLocationResolver($warehouses, $this->createMock(VenueRepositoryInterface::class), $this->rentals, $this->createMock(TourRepositoryInterface::class));
        $routing = $this->createMock(RoutingSettingsRepositoryInterface::class);
        $routing->method('get')->willReturn(new RoutingSettings());

        $plannedVehicles = $this->createMock(PlannedVehicleRepositoryInterface::class);
        $plannedVehicles->method('findByRental')->willReturnCallback(fn () => $this->planned);
        $vehicles = $this->createMock(VehicleRepositoryInterface::class);
        $vehicles->method('findById')->willReturnCallback(static fn (string $id) => new Vehicle($id, VehicleCategory::Auto, 'Sprinter', null, null, null, null, null, false, false, null, null, null, $now, $now, costPerKm: '0.40'));
        $settings = $this->createMock(VehicleSettingsRepositoryInterface::class);
        $settings->method('get')->willReturn(new VehicleSettings('0.30', '0.15', '10.00'));

        $this->service = new PlannedTripService($this->trips, $this->rentals, $resolver, new LogbookDistanceService($routing), new TravelCostRate($plannedVehicles, $vehicles, $settings));
    }

    public function testDefaultTripsAreCreatedOnceTheEventHasALocation(): void
    {
        $rental = $this->rental(location: null);
        $this->assertSame([], $this->service->forRental($rental));
        $this->assertFalse($this->rentals->findById('r1')?->plannedTripsInitialized);

        $rental->eventLocation = 'Stadthalle';
        $trips = $this->service->forRental($rental);

        $this->assertCount(2, $trips);
        $this->assertSame(PlannedTrip::OUTBOUND, $trips[0]->defaultKind);
        $this->assertSame('Hauptlager', $trips[0]->from?->label);
        $this->assertSame(LogbookLocationType::EventVenue, $trips[0]->to?->type);
        $this->assertSame('2026-12-01', $trips[0]->tripDate?->format('Y-m-d'));
        $this->assertSame(PlannedTrip::RETURN, $trips[1]->defaultKind);
        $this->assertSame('Hauptlager', $trips[1]->to?->label);
        $this->assertSame('2026-12-03', $trips[1]->tripDate?->format('Y-m-d'));
        $this->assertTrue($this->rentals->findById('r1')?->plannedTripsInitialized);
    }

    public function testUnchangedDefaultTripsFollowTheEvent(): void
    {
        $rental = $this->rental();
        $this->service->forRental($rental);

        $rental->returnWarehouseId = 'wh-2';
        $rental->returnAt = new DateTimeImmutable('2026-12-04 10:00');
        $trips = $this->service->forRental($rental);

        $this->assertSame('Außenlager', $trips[1]->to?->label);
        $this->assertSame('2026-12-04', $trips[1]->tripDate?->format('Y-m-d'));
        $this->assertSame('Hauptlager', $trips[0]->from?->label);
    }

    public function testEditedTripsNoLongerFollowAndKmOverrideWins(): void
    {
        $rental = $this->rental();
        $outbound = $this->service->forRental($rental)[0];
        $this->service->save('r1', $outbound->id, '2026-11-30', 'warehouse:wh-1', null, 'event_venue:r1', null, '42');

        $rental->pickupWarehouseId = 'wh-2';
        $trips = $this->service->forRental($rental);
        $edited = $this->trips->findById($outbound->id);
        $this->assertNull($edited?->defaultKind);
        $this->assertSame('Hauptlager', $edited?->from?->label);
        $this->assertSame('2026-11-30', $edited?->tripDate?->format('Y-m-d'));
        $this->assertSame(42, $edited?->kilometers());
        $this->assertCount(2, $trips);
    }

    public function testDeletedDefaultsStayDeletedUntilRestored(): void
    {
        $rental = $this->rental();
        foreach ($this->service->forRental($rental) as $trip) {
            $this->service->delete('r1', $trip->id);
        }
        $this->assertSame([], $this->service->forRental($rental));

        $this->service->save('r1', null, '2026-12-02', 'warehouse:wh-1', null, 'custom:', 'Baumarkt', '15');
        $this->assertSame(2, $this->service->restoreDefaults('r1'));
        $trips = $this->service->forRental($rental);
        $this->assertCount(3, $trips);
        $this->assertSame(['outbound', null, 'return'], array_map(static fn (PlannedTrip $t) => $t->defaultKind, $trips));
    }

    public function testRestoringReplacesEditedDefaultTrips(): void
    {
        $rental = $this->rental();
        $outbound = $this->service->forRental($rental)[0];
        $this->service->save('r1', $outbound->id, '2026-12-01', 'warehouse:wh-1', null, 'event_venue:r1', null, '18');
        $this->assertTrue($this->trips->findById($outbound->id)?->wasDefault());

        $this->service->restoreDefaults('r1');
        $trips = $this->service->forRental($rental);
        $this->assertSame(['outbound', 'return'], array_map(static fn (PlannedTrip $t) => $t->defaultKind, $trips));
        $this->assertNull($this->trips->findById($outbound->id));
    }

    public function testValidation(): void
    {
        $this->rental();
        try {
            $this->service->save('r1', null, '', null, null, 'custom:', '', 'abc');
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['tripDate', 'from', 'to', 'kmOverride'], array_keys($e->errors()));
        }
    }

    public function testProposalUsesPlannedKilometresAndVehicles(): void
    {
        $rental = $this->rental();
        [$outbound, $return] = $this->service->forRental($rental);
        // Without a routing key there is no distance: hint, no trip.
        $proposal = $this->service->proposal($rental);
        $this->assertSame([], $proposal->trips);
        $this->assertContains('Bei 2 Fahrten fehlen die Kilometer.', $proposal->hints);
        $this->assertContains('Keine Fahrzeuge geplant – Fahrtkosten können nicht berechnet werden.', $proposal->hints);

        $this->service->save('r1', $outbound->id, '2026-12-01', 'warehouse:wh-1', null, 'event_venue:r1', null, '40');
        $this->service->save('r1', $return->id, '2026-12-03', 'event_venue:r1', null, 'warehouse:wh-1', null, '41');
        $this->planned = [new PlannedVehicle('p1', 'r1', 'v1')];
        $proposal = $this->service->proposal($rental);

        $this->assertSame(81, $proposal->totalKilometers());
        $this->assertSame('0.44', $proposal->ratePerKm); // 0.40 + 10 %
        $this->assertSame(35.64, $proposal->total());
        $this->assertFalse($proposal->enabled);
    }

    public function testToursHaveNoPlannedTrips(): void
    {
        $rental = $this->rental();
        $rental->isTour = true;
        $this->assertSame([], $this->service->forRental($rental));
        $this->expectException(ValidationException::class);
        $this->service->restoreDefaults('r1');
    }

    private function rental(?string $location = 'Stadthalle'): Rental
    {
        $now = new DateTimeImmutable();
        $rental = new Rental('r1', 'c1', new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-03'), 'Fest', $location, null, 3, $now, $now, pickupWarehouseId: 'wh-1', travelCostsEnabled: false);
        $this->rentals->save($rental);

        return $rental;
    }
}
