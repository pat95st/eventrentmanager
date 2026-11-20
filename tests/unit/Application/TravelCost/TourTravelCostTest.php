<?php

declare(strict_types=1);

namespace Tests\Unit\Application\TravelCost;

use App\Application\Routing\TourRoute;
use App\Application\TravelCost\TourTravelCostCalculator;
use App\Domain\Pricing\DocumentTotals;
use App\Domain\Rental\Rental;
use App\Domain\Routing\PlannedVehicle;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Routing\RouteLeg;
use App\Domain\Routing\RoutePoint;
use App\Domain\Routing\RouteProfile;
use App\Domain\Routing\RouteResult;
use App\Domain\Routing\VehicleRestrictions;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\TourStopKind;
use App\Domain\TravelCost\TravelCostDocumentType;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostSection;
use App\Domain\TravelCost\TravelCostTrip;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleSettings;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryTourRepository;
use Tests\Unit\Domain\Routing\RoutingDomainTest;

/** Travel costs of tours (migration 182): trips, price per km and document lines. */
final class TourTravelCostTest extends Unit
{
    private Rental $rental;
    private InMemoryTourRepository $tours;
    /** @var PlannedVehicle[] */
    private array $planned = [];
    /** @var array<string, Vehicle> */
    private array $vehicles = [];
    private VehicleSettings $settings;

    protected function _before(): void
    {
        $now = new DateTimeImmutable();
        $this->rental = new Rental('tour', 'c', new DateTimeImmutable('2027-06-01'), new DateTimeImmutable('2027-06-03'), 'Tour', null, null, 3, $now, $now, isTour: true);
        $this->tours = new InMemoryTourRepository();
        $this->tours->saveStop(new TourStop('s1', 'tour', TourStopKind::Stop, locationText: 'Hamburg', eventStartAt: new DateTimeImmutable('2027-06-01 20:00'), teardownEndAt: new DateTimeImmutable('2027-06-02 02:00')));
        $this->tours->saveStop(new TourStop('w1', 'tour', TourStopKind::Waypoint, afterStopId: 's1', label: 'Hotel'));
        $this->tours->saveStop(new TourStop('s2', 'tour', TourStopKind::Stop, locationText: 'Kassel', setupAt: new DateTimeImmutable('2027-06-02 12:00'), teardownEndAt: new DateTimeImmutable('2027-06-03 01:00')));
        $this->vehicles['van'] = RoutingDomainTest::vehicle('van', VehicleCategory::Auto, null, null, null, null);
        $this->vehicles['trailer'] = RoutingDomainTest::vehicle('trailer', VehicleCategory::Anhaenger, null, null, null, null);
        $this->settings = new VehicleSettings();
    }

    /**
     * Start (default warehouse) → Hamburg → Hotel (waypoint) → Kassel → destination (default warehouse).
     *
     * @param array<string, array{notBilled?: bool, km?: int}> $legOptions by stop id of the arrival point
     */
    private function route(array $legOptions = [], bool $straightLine = false): TourRoute
    {
        $point = static fn (string $label, string $kind, ?string $stopId) => new RoutePoint(
            $label,
            50.0,
            9.0,
            $kind,
            $stopId,
            $legOptions[$stopId ?? '']['notBilled'] ?? false,
            $legOptions[$stopId ?? '']['km'] ?? null,
        );
        $points = [
            $point('Start: Hauptlager', 'start', null),
            $point('Tourstop 1: Hamburg', 'stop', 's1'),
            $point('Übernachtung: Hotel', 'waypoint', 'w1'),
            $point('Tourstop 2: Kassel', 'stop', 's2'),
            $point('Ziel: Nord', 'end', null),
        ];
        $legs = [];
        foreach ([100400.0, 50300.0, 60400.0, 200600.0] as $i => $meters) {
            $legs[] = new RouteLeg($i, $i + 1, $meters, $straightLine ? null : 3600.0, []);
        }

        return new TourRoute($this->rental, $points, new RouteResult($legs, $straightLine), RouteProfile::Car, new VehicleRestrictions(), [], [], [], true, 30);
    }

    private function calculator(): TourTravelCostCalculator
    {
        $planned = $this->createMock(PlannedVehicleRepositoryInterface::class);
        $planned->method('findByRental')->willReturnCallback(fn () => $this->planned);
        $vehicles = $this->createMock(VehicleRepositoryInterface::class);
        $vehicles->method('findById')->willReturnCallback(fn (string $id) => $this->vehicles[$id] ?? null);
        $settings = $this->createMock(VehicleSettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(fn () => $this->settings);

        return new TourTravelCostCalculator($this->tours, $planned, $vehicles, $settings);
    }

    public function testTripsGroupWaypointsAndRoundToWholeKilometres(): void
    {
        $this->planned = [new PlannedVehicle('p1', 'tour', 'van')];

        $proposal = $this->calculator()->fromRoute($this->route());

        $this->assertSame(
            [['Hauptlager', 'Hamburg', 100], ['Hamburg', 'Kassel', 111], ['Kassel', 'Lager Nord', 201]],
            array_map(static fn (TravelCostTrip $t) => [$t->from, $t->to, $t->kilometers], $proposal->trips),
        );
        // Departure: rental start for the default warehouse, teardown end at the stops.
        $this->assertSame(['2027-06-01', '2027-06-02', '2027-06-03'], array_map(static fn (TravelCostTrip $t) => $t->date?->format('Y-m-d'), $proposal->trips));
        $this->assertSame('Hauptlager → Hamburg → Kassel → Lager Nord', $proposal->routeLabel());
        $this->assertSame(412, $proposal->totalKilometers());
        $this->assertSame('0.30', $proposal->ratePerKm);
        $this->assertEqualsWithDelta(123.60, $proposal->total(), 0.001);
        $this->assertTrue($proposal->isBillable());
    }

    public function testLegsNotBilledAndKilometresEnteredByHand(): void
    {
        $this->planned = [new PlannedVehicle('p1', 'tour', 'van')];

        // Drive to the hotel not billed, drive to Kassel 70 km by hand.
        $proposal = $this->calculator()->fromRoute($this->route(['w1' => ['notBilled' => true], 's2' => ['km' => 70]]));
        $this->assertSame([100, 70, 201], array_map(static fn (TravelCostTrip $t) => $t->kilometers, $proposal->trips));

        // A trip without any billed leg is left out.
        $proposal = $this->calculator()->fromRoute($this->route(['w1' => ['notBilled' => true], 's2' => ['notBilled' => true]]));
        $this->assertSame([['Hauptlager', 'Hamburg'], ['Kassel', 'Lager Nord']], array_map(static fn (TravelCostTrip $t) => [$t->from, $t->to], $proposal->trips));
    }

    public function testRateIsSumOfPlannedVehiclesPlusMarkupRoundedToCents(): void
    {
        // The towing car is planned once on its own, the trailer references it.
        $this->planned = [new PlannedVehicle('p1', 'tour', 'trailer', 'van'), new PlannedVehicle('p2', 'tour', 'van')];
        $this->settings = new VehicleSettings('0.30', '0.15', '10.00');

        $proposal = $this->calculator()->fromRoute($this->route());

        $this->assertSame('0.45', $proposal->costPerKm);
        $this->assertSame('0.50', $proposal->ratePerKm); // 0.495 → 0.50
        $this->assertSame('Fahrzeug trailer + Fahrzeug van', $proposal->vehiclesLabel());
    }

    public function testWithoutPlannedVehiclesNothingIsBillable(): void
    {
        $proposal = $this->calculator()->fromRoute($this->route());

        $this->assertFalse($proposal->isBillable());
        $this->assertStringContainsString('Keine Fahrzeuge geplant', implode(' ', $proposal->hints));
    }

    public function testDisabledTourIsNotBillableAndStraightLineIsPointedOut(): void
    {
        $this->planned = [new PlannedVehicle('p1', 'tour', 'van')];
        $this->rental->travelCostsEnabled = false;

        $proposal = $this->calculator()->fromRoute($this->route([], straightLine: true));

        $this->assertFalse($proposal->isBillable());
        $this->assertStringContainsString('Luftlinie', implode(' ', $proposal->hints));
    }

    public function testSummaryAndItemizedLines(): void
    {
        $this->planned = [new PlannedVehicle('p1', 'tour', 'van')];
        $proposal = $this->calculator()->fromRoute($this->route());

        $section = $proposal->toSection(TravelCostDocumentType::Invoice, 'inv-1');
        $this->assertSame(TravelCostMode::Summary, $section->mode);
        $this->assertCount(1, $section->lines);
        $this->assertSame('Fahrtkosten Tour (3 Fahrten), Fahrzeug van', $section->lines[0]->description);
        $this->assertSame('Hauptlager → Hamburg → Kassel → Lager Nord', $section->lines[0]->detail);
        $this->assertSame('412.00', $section->lines[0]->kilometers);
        $this->assertEqualsWithDelta(123.60, $section->total(), 0.001);

        $section->mode = TravelCostMode::Itemized;
        $section->rebuildLines();
        $this->assertSame(['Hauptlager → Hamburg', 'Hamburg → Kassel', 'Kassel → Lager Nord'], array_map(static fn ($l) => $l->description, $section->lines));
        $this->assertSame('2027-06-02', $section->lines[1]->tripDate?->format('Y-m-d'));
        // Both presentations give the same amount.
        $this->assertEqualsWithDelta(123.60, $section->total(), 0.001);

        // Trips survive the JSON round trip of the repository.
        $this->assertEquals($section->trips, TravelCostSection::tripsFromJson($section->tripsJson()));

        $copy = $section->copyFor(TravelCostDocumentType::Offer, 'off-1');
        $this->assertNotSame($section->id, $copy->id);
        $this->assertSame('off-1', $copy->documentId);
        $this->assertEqualsWithDelta($section->total(), $copy->total(), 0.001);
    }

    public function testTravelCostsAreAddedAfterTheDiscount(): void
    {
        $totals = DocumentTotals::calculate(
            rentalNetTotal: 1000.0,
            accessoryFlatRateEnabled: true,
            accessoryFlatRatePercent: '10',
            discountType: 'percent',
            discountValue: '10',
            laborTimeTotal: 0.0,
            isSmallBusiness: false,
            vatRatePercent: '19',
            travelCostTotal: 123.60,
        );

        // (1000 + 100 accessory) − 110 discount = 990, + 123.60 travel costs (no accessory, no discount).
        $this->assertEqualsWithDelta(1113.60, $totals->netTotal, 0.001);
        $this->assertEqualsWithDelta(123.60, $totals->travelCostTotal, 0.001);
        $this->assertEqualsWithDelta(211.58, $totals->taxAmount, 0.001);
    }
}
