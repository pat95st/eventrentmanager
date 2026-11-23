<?php

declare(strict_types=1);

namespace Tests\Unit\Application\TravelCost;

use App\Application\PlannedTrip\PlannedTripService;
use App\Application\TravelCost\ActualTravelCostCalculator;
use App\Application\TravelCost\DocumentTravelCostService;
use App\Application\TravelCost\TourTravelCostCalculator;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookEntry;
use App\Domain\Logbook\LogbookLocation;
use App\Domain\Logbook\LogbookLocationType;
use App\Domain\Logbook\LogbookStatus;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Offer\OfferStatus;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\TravelCost\TravelCostBasis;
use App\Domain\TravelCost\TravelCostDocumentType;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostSection;
use App\Domain\TravelCost\TravelCostTrip;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleSettings;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use App\Infrastructure\Pdf\TravelCostPdfRows;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryLogbookEntryRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;
use Tests\Support\Fakes\InMemoryTourRepository;
use Tests\Support\Fakes\InMemoryTravelCostSectionRepository;

/** Travel costs from the kilometres actually driven (migration 187). */
final class ActualTravelCostTest extends Unit
{
    private InMemoryLogbookEntryRepository $logbook;
    private InMemoryRentalRepository $rentals;
    private InMemoryTravelCostSectionRepository $sections;
    private ActualTravelCostCalculator $calculator;
    private VehicleSettings $settings;
    private Invoice $invoice;
    private Offer $offer;
    /** @var array<string, \App\Application\TravelCost\TourTravelCostProposal> */
    private array $plannedProposals = [];

    protected function _before(): void
    {
        $now = new DateTimeImmutable();
        $this->logbook = new InMemoryLogbookEntryRepository();
        $this->rentals = new InMemoryRentalRepository();
        $this->rentals->save(new Rental('r1', 'c1', new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-02'), 'Fest', 'Halle', null, 2, $now, $now, travelCostsEnabled: true));
        $this->sections = new InMemoryTravelCostSectionRepository();
        $this->settings = new VehicleSettings('0.30', '0.15', '10.00');
        $vehicles = [
            'sprinter' => new Vehicle('sprinter', VehicleCategory::Auto, 'Sprinter', null, null, null, null, null, false, false, null, null, null, $now, $now, costPerKm: '0.50'),
            'golf' => new Vehicle('golf', VehicleCategory::Auto, 'Golf', null, null, null, null, null, false, false, null, null, null, $now, $now),
            'trailer' => new Vehicle('trailer', VehicleCategory::Anhaenger, 'Anhänger', null, null, null, null, null, false, null, null, null, null, $now, $now),
        ];
        $vehicleRepo = $this->createMock(VehicleRepositoryInterface::class);
        $vehicleRepo->method('findById')->willReturnCallback(static fn (string $id) => $vehicles[$id] ?? null);
        $settingsRepo = $this->createMock(VehicleSettingsRepositoryInterface::class);
        $settingsRepo->method('get')->willReturnCallback(fn () => $this->settings);
        $this->calculator = new ActualTravelCostCalculator($this->logbook, $vehicleRepo, $settingsRepo);
        $this->invoice = new Invoice('inv-1', 'c1', null, null, null, null, null, null, null, null, InvoiceStatus::Draft, null, null, null, null, null, 'r1', $now, $now);
        $this->offer = new Offer('of-1', 'A-1', null, null, null, null, null, null, OfferStatus::Draft, null, null, $now, $now, sourceRentalId: 'r1');
    }

    public function testEveryTripIsBilledWithItsOwnVehicle(): void
    {
        $this->trip('e1', 'sprinter', null, 100, '2026-12-01');
        $this->trip('e2', 'golf', 'trailer', 40, '2026-12-01', roundTrip: true);
        $this->trip('e3', null, null, 10, '2026-12-02');

        $proposal = $this->calculator->forRental($this->rentals->findById('r1'));

        $this->assertSame(TravelCostBasis::Actual, $proposal->basis);
        $rates = array_map(static fn (TravelCostTrip $t) => [$t->ratePerKm, $t->vehicle, $t->kilometers], $proposal->trips);
        $this->assertSame([
            ['0.55', 'Sprinter', 100],            // 0.50 + 10 %
            ['0.50', 'Golf mit Anhänger', 40],    // (0.30 + 0.15) + 10 %
            ['0.33', 'Bus', 10],                  // other vehicle: default for cars
        ], $rates);
        $this->assertSame('Halle und zurück', $proposal->trips[1]->to);
        $this->assertEqualsWithDelta(55.0 + 20.0 + 3.3, $proposal->total(), 0.001);
    }

    public function testOnlyBillableTripsCount(): void
    {
        $this->trip('ok', 'sprinter', null, 100, '2026-12-01');
        $this->trip('notBilled', 'sprinter', null, 50, '2026-12-01', notBilled: true);
        $this->trip('pending', 'sprinter', null, 50, '2026-12-01', status: LogbookStatus::Pending);
        $this->trip('private', 'sprinter', null, 50, '2026-12-01', category: LogbookCategory::Private);
        $this->trip('deleted', 'sprinter', null, 50, '2026-12-01', deleted: true);

        $proposal = $this->calculator->forRental($this->rentals->findById('r1'));
        $this->assertSame(100, $proposal->totalKilometers());
    }

    public function testSummaryHasOneLinePerRateAndItemizedShowsVehicles(): void
    {
        $this->trip('e1', 'sprinter', null, 100, '2026-12-01');
        $this->trip('e2', 'sprinter', null, 90, '2026-12-02');
        $this->trip('e3', 'golf', null, 20, '2026-12-02');
        $section = $this->calculator->forRental($this->rentals->findById('r1'))->toSection(TravelCostDocumentType::Invoice, 'inv-1');

        $this->assertCount(2, $section->lines);
        $this->assertSame('Fahrtkosten, gefahrene Kilometer (2 Fahrten), Sprinter', $section->lines[0]->description);
        $this->assertSame(['190.00', '0.55'], [$section->lines[0]->kilometers, $section->lines[0]->ratePerKm]);
        $this->assertSame(['20.00', '0.33'], [$section->lines[1]->kilometers, $section->lines[1]->ratePerKm]);
        $this->assertEqualsWithDelta(104.5 + 6.6, $section->total(), 0.001);

        $section->mode = TravelCostMode::Itemized;
        $section->rebuildLines();
        $this->assertCount(3, $section->lines);
        $this->assertSame('Golf', $section->lines[2]->detail);
        $this->assertSame('0.33', $section->lines[2]->ratePerKm);

        // Rates survive storage as JSON (switching the mode later).
        $trips = TravelCostSection::tripsFromJson($section->tripsJson());
        $this->assertSame('0.55', $trips[0]->ratePerKm);
        $this->assertSame('Sprinter', $trips[0]->vehicle);
    }

    public function testInvoicesChooseDrivenOrPlannedKilometres(): void
    {
        $this->plannedProposals['r1'] = $this->planned(80);
        $service = $this->service();
        $this->assertSame('planned', $service->choices(TravelCostDocumentType::Invoice, 'inv-1')['preselect']);

        $this->trip('e1', 'sprinter', null, 100, '2026-12-01');
        $choices = $service->choices(TravelCostDocumentType::Invoice, 'inv-1');
        $this->assertSame('actual', $choices['preselect']);
        $this->assertSame(100, $choices['actual']?->totalKilometers());

        $section = $service->import(TravelCostDocumentType::Invoice, 'inv-1', 'actual');
        $this->assertSame(TravelCostBasis::Actual, $section?->basis);
        $this->assertNull($section?->estimateNote);
        $this->assertFalse($service->logbookChanged($section));

        $this->trip('e2', 'sprinter', null, 30, '2026-12-02');
        $this->assertTrue($service->logbookChanged($this->sections->findFor(TravelCostDocumentType::Invoice, 'inv-1')));

        $planned = $service->import(TravelCostDocumentType::Invoice, 'inv-1', 'planned');
        $this->assertSame(80, (int) $planned?->totalKilometers());

        $this->assertNull($service->import(TravelCostDocumentType::Invoice, 'inv-1', 'none'));
        $this->assertNull($this->sections->findFor(TravelCostDocumentType::Invoice, 'inv-1'));
    }

    public function testOffersOnlyBillPlannedKilometresWithNote(): void
    {
        $this->plannedProposals['r1'] = $this->planned(80);
        $service = $this->service();
        $this->trip('e1', 'sprinter', null, 100, '2026-12-01');

        $this->assertNull($service->choices(TravelCostDocumentType::Offer, 'of-1')['actual']);
        try {
            $service->import(TravelCostDocumentType::Offer, 'of-1', 'actual');
            $this->fail('ValidationException expected');
        } catch (ValidationException) {
        }

        $section = $service->import(TravelCostDocumentType::Offer, 'of-1', 'planned');
        $this->assertSame(VehicleSettings::DEFAULT_ESTIMATE_NOTE, $section?->estimateNote);
        $this->assertSame(VehicleSettings::DEFAULT_ESTIMATE_NOTE, TravelCostPdfRows::estimateNote($section));

        $service->setEstimateNote(TravelCostDocumentType::Offer, 'of-1', false);
        $this->assertNull(TravelCostPdfRows::estimateNote($this->sections->findFor(TravelCostDocumentType::Offer, 'of-1')));
    }

    public function testNewInvoiceFromEventPrefersDrivenKilometres(): void
    {
        $this->plannedProposals['r1'] = $this->planned(80);
        $service = $this->service();
        $service->importForNewDocument(TravelCostDocumentType::Invoice, 'inv-1', 'r1');
        $this->assertSame(TravelCostBasis::Planned, $this->sections->findFor(TravelCostDocumentType::Invoice, 'inv-1')?->basis);

        $this->trip('e1', 'sprinter', null, 100, '2026-12-01');
        $service->importForNewDocument(TravelCostDocumentType::Invoice, 'inv-1', 'r1');
        $this->assertSame(TravelCostBasis::Actual, $this->sections->findFor(TravelCostDocumentType::Invoice, 'inv-1')?->basis);

        $this->rentals->findById('r1')->travelCostsEnabled = false;
        $this->sections->deleteFor(TravelCostDocumentType::Invoice, 'inv-1');
        $service->importForNewDocument(TravelCostDocumentType::Invoice, 'inv-1', 'r1');
        $this->assertNull($this->sections->findFor(TravelCostDocumentType::Invoice, 'inv-1'));
    }

    private function service(): DocumentTravelCostService
    {
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('findById')->willReturnCallback(fn (string $id) => $id === 'inv-1' ? $this->invoice : null);
        $offers = $this->createMock(OfferRepositoryInterface::class);
        $offers->method('findById')->willReturnCallback(fn (string $id) => $id === 'of-1' ? $this->offer : null);
        $settings = $this->createMock(VehicleSettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(fn () => $this->settings);
        // Real planned trips: one trip with the planned kilometres, one planned vehicle (0.50 €/km).
        $tripRepo = new \Tests\Support\Fakes\InMemoryPlannedTripRepository();
        $rental = $this->rentals->findById('r1');
        $rental->plannedTripsInitialized = true;
        $km = isset($this->plannedProposals['r1']) ? $this->plannedProposals['r1']->totalKilometers() : 0;
        if ($km > 0) {
            $now = new DateTimeImmutable();
            $tripRepo->save(new \App\Domain\PlannedTrip\PlannedTrip('pt1', 'r1', 0, new DateTimeImmutable('2026-12-01'), new LogbookLocation(LogbookLocationType::Warehouse, 'wh', 'Lager'), new LogbookLocation(LogbookLocationType::EventVenue, 'r1', 'Halle'), null, $km, null, null, $now, $now));
        }
        $plannedVehicles = $this->createMock(PlannedVehicleRepositoryInterface::class);
        $plannedVehicles->method('findByRental')->willReturn([new \App\Domain\Routing\PlannedVehicle('pv', 'r1', 'sprinter')]);
        $vehicleRepo = $this->createMock(VehicleRepositoryInterface::class);
        $vehicleRepo->method('findById')->willReturn(new Vehicle('sprinter', VehicleCategory::Auto, 'Sprinter', null, null, null, null, null, false, false, null, null, null, new DateTimeImmutable(), new DateTimeImmutable(), costPerKm: '0.50'));
        $routing = $this->createMock(\App\Domain\Routing\RoutingSettingsRepositoryInterface::class);
        $routing->method('get')->willReturn(new \App\Domain\Routing\RoutingSettings());
        $planned = new PlannedTripService(
            $tripRepo,
            $this->rentals,
            new \App\Application\Logbook\LogbookLocationResolver($this->createMock(\App\Domain\Warehouse\WarehouseRepositoryInterface::class), $this->createMock(\App\Domain\Venue\VenueRepositoryInterface::class), $this->rentals, new InMemoryTourRepository()),
            new \App\Application\Logbook\LogbookDistanceService($routing),
            new \App\Application\TravelCost\TravelCostRate($plannedVehicles, $vehicleRepo, $settings),
        );

        return new DocumentTravelCostService(
            $this->sections,
            new TourTravelCostCalculator(new InMemoryTourRepository(), $this->createMock(PlannedVehicleRepositoryInterface::class), $this->createMock(VehicleRepositoryInterface::class), $settings),
            $offers,
            $this->createMock(OrderConfirmationRepositoryInterface::class),
            $invoices,
            $this->rentals,
            $planned,
            $this->calculator,
            $settings,
        );
    }

    private function planned(int $km): \App\Application\TravelCost\TourTravelCostProposal
    {
        $rental = $this->rentals->findById('r1');

        return new \App\Application\TravelCost\TourTravelCostProposal(
            rentalId: 'r1',
            enabled: $rental?->travelCostsEnabled ?? false,
            mode: TravelCostMode::Summary,
            trips: $km > 0 ? [new TravelCostTrip(new DateTimeImmutable('2026-12-01'), 'Lager', 'Halle', $km)] : [],
            vehicles: [['name' => 'Sprinter', 'costPerKm' => '0.50', 'isDefault' => false]],
            costPerKm: '0.50',
            markupPercent: '10.00',
            ratePerKm: '0.55',
            hints: [],
            basis: TravelCostBasis::Planned,
        );
    }

    private function trip(string $id, ?string $vehicle, ?string $trailer, int $km, string $date, bool $roundTrip = false, bool $notBilled = false, LogbookStatus $status = LogbookStatus::Approved, LogbookCategory $category = LogbookCategory::Event, bool $deleted = false): void
    {
        $now = new DateTimeImmutable();
        $this->logbook->save(new LogbookEntry(
            $id, new DateTimeImmutable($date), null, null, 'u1', null, null, false, $vehicle, $vehicle === null ? 'Bus' : null, $trailer,
            $category, 'r1', null, null,
            new LogbookLocation(LogbookLocationType::Warehouse, 'wh', 'Lager'), new LogbookLocation(LogbookLocationType::EventVenue, 'r1', 'Halle'),
            $roundTrip, $km, null, null, $notBilled, $status, false, 'u1', $now, $now, $deleted ? $now : null,
        ));
    }
}
