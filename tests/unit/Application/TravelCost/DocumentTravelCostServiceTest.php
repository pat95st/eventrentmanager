<?php

declare(strict_types=1);

namespace Tests\Unit\Application\TravelCost;

use App\Application\Routing\Command\RoutingCommandHandler;
use App\Application\Routing\Command\SetLegKilometers;
use App\Application\Routing\Command\SetTourTravelCosts;
use App\Application\TravelCost\DocumentTravelCostService;
use App\Application\TravelCost\TourTravelCostCalculator;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Offer\OfferStatus;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\TourStopKind;
use App\Domain\TravelCost\TravelCostDocumentType;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostSection;
use App\Domain\TravelCost\TravelCostTrip;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryRentalRepository;
use Tests\Support\Fakes\InMemoryTourRepository;
use Tests\Support\Fakes\InMemoryTravelCostSectionRepository;

/** Travel cost section on documents and the tour settings (migration 182). */
final class DocumentTravelCostServiceTest extends Unit
{
    private InMemoryTravelCostSectionRepository $sections;
    private Offer $offer;
    private InMemoryRentalRepository $rentals;
    private InMemoryTourRepository $tours;

    protected function _before(): void
    {
        $now = new DateTimeImmutable();
        $this->sections = new InMemoryTravelCostSectionRepository();
        $this->offer = new Offer('of-1', 'A-1001', null, null, null, null, null, null, OfferStatus::Draft, null, null, $now, $now);
        $this->rentals = new InMemoryRentalRepository();
        $this->rentals->save(new Rental('tour', 'c', new DateTimeImmutable('2027-06-01'), new DateTimeImmutable('2027-06-03'), 'Tour', null, null, 3, $now, $now, isTour: true));
        $this->tours = new InMemoryTourRepository();
        $trips = [
            new TravelCostTrip(new DateTimeImmutable('2027-06-01'), 'Lager', 'Hamburg', 100),
            new TravelCostTrip(new DateTimeImmutable('2027-06-02'), 'Hamburg', 'Lager', 120),
        ];
        $section = new TravelCostSection('tc-1', TravelCostDocumentType::Offer, 'of-1', TravelCostMode::Summary, 'Sprinter', 'Lager → Hamburg → Lager', '0.45', $trips);
        $section->rebuildLines();
        $this->sections->save($section);
    }

    private function service(): DocumentTravelCostService
    {
        $offers = $this->createMock(OfferRepositoryInterface::class);
        $offers->method('findById')->willReturnCallback(fn (string $id) => $id === $this->offer->id ? $this->offer : null);
        $calculator = new TourTravelCostCalculator(
            $this->tours,
            $this->createMock(PlannedVehicleRepositoryInterface::class),
            $this->createMock(VehicleRepositoryInterface::class),
            $this->createMock(VehicleSettingsRepositoryInterface::class),
        );

        return new DocumentTravelCostService(
            $this->sections,
            $calculator,
            $offers,
            $this->createMock(OrderConfirmationRepositoryInterface::class),
            $this->createMock(InvoiceRepositoryInterface::class),
            $this->rentals,
        );
    }

    public function testSwitchingTheModeRebuildsTheLinesWithTheSameAmount(): void
    {
        $this->assertEqualsWithDelta(99.00, $this->sections->findFor(TravelCostDocumentType::Offer, 'of-1')?->total() ?? 0.0, 0.001);

        $this->service()->setMode(TravelCostDocumentType::Offer, 'of-1', 'itemized');

        $section = $this->sections->findFor(TravelCostDocumentType::Offer, 'of-1');
        $this->assertSame(TravelCostMode::Itemized, $section?->mode);
        $this->assertCount(2, $section->lines);
        $this->assertEqualsWithDelta(99.00, $section->total(), 0.001);
    }

    public function testLinesCanBeEditedAndEmptyRowsAreIgnored(): void
    {
        $this->service()->saveLines(TravelCostDocumentType::Offer, 'of-1', [
            ['date' => '2027-06-01', 'description' => 'Pauschale Anfahrt', 'detail' => '', 'km' => '1.234,5', 'rate' => '0,40'],
            ['date' => '', 'description' => '', 'detail' => '', 'km' => '', 'rate' => '0,45'],
        ]);

        $section = $this->sections->findFor(TravelCostDocumentType::Offer, 'of-1');
        $this->assertCount(1, $section?->lines ?? []);
        $this->assertSame('1234.50', $section->lines[0]->kilometers);
        $this->assertSame('0.40', $section->lines[0]->ratePerKm);
        $this->assertEqualsWithDelta(493.80, $section->total(), 0.001);
    }

    public function testInvalidLinesAreRejected(): void
    {
        try {
            $this->service()->saveLines(TravelCostDocumentType::Offer, 'of-1', [['description' => 'Fahrt', 'km' => 'viel', 'rate' => '0,45']]);
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines.0.km', $e->errors());
        }
    }

    public function testWithoutLinesTheSectionIsRemoved(): void
    {
        $this->service()->saveLines(TravelCostDocumentType::Offer, 'of-1', [['description' => '', 'km' => '', 'rate' => '0,45']]);

        $this->assertNull($this->sections->findFor(TravelCostDocumentType::Offer, 'of-1'));
    }

    public function testSentDocumentsCannotBeChanged(): void
    {
        $this->offer->status = OfferStatus::Sent;

        $this->expectException(ValidationException::class);
        $this->service()->setMode(TravelCostDocumentType::Offer, 'of-1', 'itemized');
    }

    public function testImportNeedsALinkedEvent(): void
    {
        try {
            $this->service()->importFromTour(TravelCostDocumentType::Offer, 'of-1');
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('keiner Veranstaltung', implode(' ', $e->errors()));
        }
    }

    public function testCopyAlongTheDocumentChain(): void
    {
        $this->service()->copy(TravelCostDocumentType::Offer, 'of-1', TravelCostDocumentType::Invoice, 'inv-1');

        $copy = $this->sections->findFor(TravelCostDocumentType::Invoice, 'inv-1');
        $this->assertNotNull($copy);
        $this->assertEqualsWithDelta(99.00, $copy->total(), 0.001);
        // Nothing to copy: no section is created.
        $this->service()->copy(TravelCostDocumentType::Offer, 'of-2', TravelCostDocumentType::Invoice, 'inv-2');
        $this->assertNull($this->sections->findFor(TravelCostDocumentType::Invoice, 'inv-2'));
    }

    public function testTourSettingsAndKilometresByHand(): void
    {
        $this->tours->saveStop(new TourStop('s1', 'tour', TourStopKind::Stop, locationText: 'Hamburg'));
        $handler = new RoutingCommandHandler(
            $this->createMock(RoutingSettingsRepositoryInterface::class),
            $this->rentals,
            $this->createMock(PlannedVehicleRepositoryInterface::class),
            $this->createMock(VehicleRepositoryInterface::class),
            $this->tours,
        );

        $handler->handle(new SetTourTravelCosts('tour', false, 'itemized'));
        $this->assertFalse($this->rentals->findById('tour')?->travelCostsEnabled);
        $this->assertSame('itemized', $this->rentals->findById('tour')?->travelCostsMode);

        $handler->handle(new SetLegKilometers('s1', '312,4 km'));
        $this->assertSame(312, $this->tours->findStop('s1')?->arrivalKmOverride);
        $handler->handle(new SetLegKilometers('s1', ''));
        $this->assertNull($this->tours->findStop('s1')?->arrivalKmOverride);

        $this->expectException(ValidationException::class);
        $handler->handle(new SetTourTravelCosts('tour', true, 'irgendwas'));
    }
}
