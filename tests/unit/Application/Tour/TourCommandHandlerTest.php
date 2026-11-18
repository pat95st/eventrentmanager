<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tour;

use App\Application\Command\CommandBus;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\MinuteProgram\Command\CreateMinuteProgram;
use App\Application\Technician\Command\AssignTechnician;
use App\Application\Tour\Command\ApplyTourCrewChange;
use App\Application\Tour\Command\ApplyTourMaterialChange;
use App\Application\Tour\Command\CreateTourStopMinuteProgram;
use App\Application\Tour\Command\DeleteTourStop;
use App\Application\Tour\Command\MakeTour;
use App\Application\Tour\Command\RevertTour;
use App\Application\Tour\Command\SaveTourStop;
use App\Application\Tour\Command\SaveTourWaypoint;
use App\Application\Tour\Command\TourCommandHandler;
use App\Application\Tour\TourCrewNotifierInterface;
use App\Application\Tour\TourSync;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalItem;
use App\Domain\Technician\RentalTechnician;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\Tour\TourCrewAction;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\WaypointAddressType;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryRentalItemRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;
use Tests\Support\Fakes\InMemoryRentalTechnicianRepository;
use Tests\Support\Fakes\InMemoryTourRepository;
use Tests\Support\Fakes\InMemoryVenueRepository;

final class TourCommandHandlerTest extends Unit
{
    private InMemoryRentalRepository $rentals;
    private InMemoryRentalItemRepository $items;
    private InMemoryTourRepository $tours;
    private InMemoryRentalTechnicianRepository $technicians;
    private TourCommandHandler $handler;
    /** @var list<AssignTechnician> */
    private array $assigned = [];
    /** @var list<array{userId: string, stops: string[]}> */
    private array $removedNotifications = [];

    protected function _before(): void
    {
        $this->rentals = new InMemoryRentalRepository();
        $this->items = new InMemoryRentalItemRepository();
        $this->tours = new InMemoryTourRepository();
        $this->technicians = new InMemoryRentalTechnicianRepository();
        $test = $this;
        $bus = new CommandBus();
        $bus->register(AssignTechnician::class, new class ($test) implements CommandHandlerInterface {
            public function __construct(private readonly TourCommandHandlerTest $test)
            {
            }

            public function handle(CommandInterface $command): mixed
            {
                assert($command instanceof AssignTechnician);
                $this->test->recordAssignment($command);

                return null;
            }
        });
        $bus->register(CreateMinuteProgram::class, new class () implements CommandHandlerInterface {
            public function handle(CommandInterface $command): mixed
            {
                assert($command instanceof CreateMinuteProgram);

                return 'program-1';
            }
        });
        $notifier = new class ($test) implements TourCrewNotifierInterface {
            public function __construct(private readonly TourCommandHandlerTest $test)
            {
            }

            public function notifyRemoved(Rental $tour, string $userId, array $stopLabels, ?string $replacementName): void
            {
                $this->test->recordRemoval($userId, $stopLabels);
            }
        };
        $this->handler = new TourCommandHandler(
            $this->tours,
            $this->rentals,
            new TourSync($this->tours, $this->rentals, $this->items),
            new InMemoryVenueRepository(),
            technicians: $this->technicians,
            commands: $bus,
            crewNotifier: $notifier,
            now: new DateTimeImmutable('2027-05-01 12:00'),
        );

        $now = new DateTimeImmutable();
        $this->rentals->save(new Rental('tour', 'customer', new DateTimeImmutable('2027-06-01'), new DateTimeImmutable('2027-06-01'), 'Sommertour', 'Hamburg', null, 1, $now, $now));
        $this->items->save(new RentalItem('item-top', 'tour', 'top', 4, false, $now, $now));
        $this->items->save(new RentalItem('item-sub', 'tour', 'sub', 2, false, $now, $now));
    }

    public function recordAssignment(AssignTechnician $command): void
    {
        $this->assigned[] = $command;
    }

    /** @param string[] $stops */
    public function recordRemoval(string $userId, array $stops): void
    {
        $this->removedNotifications[] = ['userId' => $userId, 'stops' => $stops];
    }

    /** @return string[] stop ids in order */
    private function buildTour(): array
    {
        $this->handler->handle(new MakeTour('tour'));
        $first = array_values($this->tours->stops)[0];
        $this->handler->handle(new SaveTourStop('tour', $first->id, 'Hamburg', '2027-06-01T14:00', '2027-06-01T20:00', '2027-06-01T23:00', '2027-06-02T02:00', '', '', ''));
        $second = $this->handler->handle(new SaveTourStop('tour', null, 'Kassel', '', '2027-06-03T20:00', '2027-06-03T23:00', '', 'Frau Meier', '0561 123', ''));
        $third = $this->handler->handle(new SaveTourStop('tour', null, 'München', '', '2027-06-05T20:00', '', '', '', '', ''));

        return [$first->id, $second, $third];
    }

    public function testMakeTourTurnsLocationIntoFirstStopAndStopsDefineTheDates(): void
    {
        $this->buildTour();

        $rental = $this->rentals->findById('tour');
        $this->assertTrue($rental?->isTour);
        $this->assertCount(3, $this->tours->stops);
        $this->assertSame('2027-06-01', $rental?->startDate->format('Y-m-d'));
        $this->assertSame('2027-06-05', $rental?->endDate->format('Y-m-d'));
    }

    public function testInvalidStopTimesAreRejected(): void
    {
        $this->handler->handle(new MakeTour('tour'));

        $this->expectException(ValidationException::class);
        $this->handler->handle(new SaveTourStop('tour', null, 'Kassel', '2027-06-03T21:00', '2027-06-03T20:00', '', '', '', '', ''));
    }

    public function testBulkMaterialChangeFromAStopOnwards(): void
    {
        [$first, $second, $third] = $this->buildTour();

        $this->handler->handle(new ApplyTourMaterialChange('tour', [$second, $third], 'sub', '6', 'stops_only'));
        $this->assertSame(4, $this->tours->findItem($second, 'sub')?->delta);
        $this->assertSame(4, $this->tours->findItem($third, 'sub')?->delta);
        $this->assertNull($this->tours->findItem($first, 'sub'));
        $this->assertSame(['sub' => 4], $this->tours->extras['tour']);

        // Back to the base quantity removes the deviation.
        $this->handler->handle(new ApplyTourMaterialChange('tour', [$third], 'sub', '2', 'stops_only'));
        $this->assertNull($this->tours->findItem($third, 'sub'));
        $this->assertNotNull($this->tours->findItem($second, 'sub'));
    }

    public function testPastStopsAreLocked(): void
    {
        [$first] = $this->buildTour();
        $handler = new TourCommandHandler($this->tours, $this->rentals, new TourSync($this->tours, $this->rentals, $this->items), new InMemoryVenueRepository(), now: new DateTimeImmutable('2027-06-04 12:00'));

        $this->expectException(ValidationException::class);
        $handler->handle(new ApplyTourMaterialChange('tour', [$first], 'sub', '6', 'stops_only'));
    }

    public function testReplacementIsRequestedAndReplacedTechnicianInformed(): void
    {
        [, $second, $third] = $this->buildTour();
        $now = new DateTimeImmutable();
        $this->technicians->save(new RentalTechnician('rt-max', 'tour', 'max', RentalTechnicianStatus::Confirmed, ['trade-sound'], null, $now, $now, 0, null, $now, $now));

        $result = $this->handler->handle(new ApplyTourCrewChange('tour', [$second, $third], 'replace', 'lisa', 'max'));

        $this->assertSame(['changed' => 2, 'requested' => true], $result);
        $this->assertCount(1, $this->assigned);
        $this->assertSame('lisa', $this->assigned[0]->userId);
        $this->assertStringContainsString('Vertretung', (string) $this->assigned[0]->note);
        $this->assertSame('max', $this->removedNotifications[0]['userId']);
        $this->assertCount(2, $this->removedNotifications[0]['stops']);
        $deviations = $this->tours->findTechniciansByRental('tour');
        $this->assertCount(2, $deviations);
        $this->assertSame(TourCrewAction::Replace, $deviations[0]->action);
    }

    public function testReplacingBySomeoneAlreadyOnTheTourOnlyRemovesTheOther(): void
    {
        [, $second] = $this->buildTour();
        $now = new DateTimeImmutable();
        $this->technicians->save(new RentalTechnician('rt-max', 'tour', 'max', RentalTechnicianStatus::Confirmed, [], null, $now, $now, 0, null, $now, $now));
        $this->technicians->save(new RentalTechnician('rt-eva', 'tour', 'eva', RentalTechnicianStatus::Assigned, [], null, $now, $now, 0, null, $now, $now));

        $result = $this->handler->handle(new ApplyTourCrewChange('tour', [$second], 'replace', 'eva', 'max'));

        $this->assertFalse($result['requested']);
        $deviation = $this->tours->findTechniciansByRental('tour')[0];
        $this->assertSame(TourCrewAction::Remove, $deviation->action);
        $this->assertSame('max', $deviation->userId);
    }

    public function testWaypointWithRememberedCustomAddress(): void
    {
        [$first] = $this->buildTour();

        $id = $this->handler->handle(new SaveTourWaypoint('tour', null, 'overnight', 'custom', '', '', 'Hotel Nord', 'Hauptstr. 1', '34117', 'Kassel', '', $first, '', '', '', true));

        $waypoint = $this->tours->findStop($id);
        $this->assertSame(WaypointAddressType::Saved, $waypoint?->addressType);
        $this->assertSame($first, $waypoint?->afterStopId);
        $this->assertCount(1, $this->tours->addresses);
        $this->assertSame('Hotel Nord', array_values($this->tours->addresses)[0]->label);
    }

    public function testStopMinuteProgramAndDeletion(): void
    {
        [$first, $second] = $this->buildTour();

        $this->assertSame('program-1', $this->handler->handle(new CreateTourStopMinuteProgram($second)));
        $this->assertSame($second, $this->tours->programs['program-1']);

        $this->handler->handle(new DeleteTourStop($second));
        $this->assertCount(2, $this->tours->stops);
        unset($first);
    }

    public function testLastStopCannotBeDeletedAndRevertNeedsSingleStop(): void
    {
        [$first, $second, $third] = $this->buildTour();
        try {
            $this->handler->handle(new RevertTour('tour'));
            $this->fail('ValidationException expected');
        } catch (ValidationException) {
        }
        $this->handler->handle(new DeleteTourStop($second));
        $this->handler->handle(new DeleteTourStop($third));
        try {
            $this->handler->handle(new DeleteTourStop($first));
            $this->fail('ValidationException expected');
        } catch (ValidationException) {
        }

        $this->handler->handle(new RevertTour('tour'));
        $this->assertFalse($this->rentals->findById('tour')?->isTour);
        $this->assertSame([], array_filter($this->tours->stops, static fn (TourStop $s) => $s->rentalId === 'tour'));
    }
}
