<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Routing;

use App\Application\Routing\Command\AddPlannedVehicle;
use App\Application\Routing\Command\RoutingCommandHandler;
use App\Application\Routing\Command\SaveRoutingSettings;
use App\Application\Routing\RoutingClientInterface;
use App\Application\Routing\RoutingException;
use App\Application\Routing\TourRouteService;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\Rental;
use App\Domain\Routing\PlannedVehicle;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Routing\RouteCacheRepositoryInterface;
use App\Domain\Routing\RouteLeg;
use App\Domain\Routing\RouteProfile;
use App\Domain\Routing\RouteResult;
use App\Domain\Routing\RoutingSettings;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Routing\VehicleRestrictions;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\TourStopKind;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Venue\Venue;
use App\Domain\Warehouse\Warehouse;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryRentalRepository;
use Tests\Support\Fakes\InMemoryTourRepository;
use Tests\Support\Fakes\InMemoryVenueRepository;
use Tests\Unit\Domain\Routing\RoutingDomainTest;

final class TourRouteServiceTest extends Unit
{
    private InMemoryRentalRepository $rentals;
    private InMemoryTourRepository $tours;
    private InMemoryVenueRepository $venues;
    private RoutingSettings $settings;
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];
    /** @var array<string, PlannedVehicle> */
    private array $planned = [];
    /** @var array<string, Vehicle> */
    private array $vehicles = [];
    private int $calls = 0;
    private ?RoutingException $failWith = null;
    private ?VehicleRestrictions $sentRestrictions = null;

    protected function _before(): void
    {
        $this->rentals = new InMemoryRentalRepository();
        $this->tours = new InMemoryTourRepository();
        $this->venues = new InMemoryVenueRepository();
        $this->settings = new RoutingSettings('key', 30);
        $now = new DateTimeImmutable();
        $this->rentals->save(new Rental('tour', 'c', new DateTimeImmutable('2027-06-01'), new DateTimeImmutable('2027-06-03'), 'Tour', null, null, 1, $now, $now, isTour: true));
        foreach ([['v-hh', 'Hamburg', 53.55, 9.99], ['v-ks', 'Kassel', 51.31, 9.48]] as [$id, $name, $lat, $lng]) {
            $this->venues->save(new Venue($id, $name, 'Str. 1', '00000', $name, null, null, null, null, null, $lat, $lng, $now, $now));
        }
        $this->tours->saveStop(new TourStop('start', 'tour', TourStopKind::Start, label: 'Lager', latitude: 53.6, longitude: 10.0));
        $this->tours->saveStop(new TourStop('s1', 'tour', TourStopKind::Stop, venueId: 'v-hh', locationText: 'Hamburg', eventStartAt: new DateTimeImmutable('2027-06-01 20:00'), teardownEndAt: new DateTimeImmutable('2027-06-02 08:00')));
        $this->tours->saveStop(new TourStop('s2', 'tour', TourStopKind::Stop, venueId: 'v-ks', locationText: 'Kassel', setupAt: new DateTimeImmutable('2027-06-02 11:00'), eventStartAt: new DateTimeImmutable('2027-06-02 20:00')));
        $this->tours->saveStop(new TourStop('s3', 'tour', TourStopKind::Stop, locationText: 'Freitext-Ort', eventStartAt: new DateTimeImmutable('2027-06-03 20:00')));
    }

    private function service(): TourRouteService
    {
        $test = $this;
        $warehouses = new class () implements WarehouseRepositoryInterface {
            public function save(Warehouse $warehouse): void
            {
            }

            public function findById(string $id): ?Warehouse
            {
                return null;
            }

            public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
            {
                return [];
            }

            public function countAll(?string $search = null): int
            {
                return 0;
            }

            public function delete(string $id): void
            {
            }
        };
        $client = new class ($test) implements RoutingClientInterface {
            public function __construct(private readonly TourRouteServiceTest $test)
            {
            }

            public function route(string $apiKey, array $points, RouteProfile $profile, VehicleRestrictions $restrictions): RouteResult
            {
                return $this->test->fakeRoute($points, $restrictions);
            }
        };

        return new TourRouteService($this->rentals, $this->tours, $this->venues, $warehouses, $this->plannedRepository(), $this->vehicleRepository(), $this->settingsRepository(), $this->cacheRepository(), $client);
    }

    /** @param \App\Domain\Routing\RoutePoint[] $points */
    public function fakeRoute(array $points, VehicleRestrictions $restrictions): RouteResult
    {
        $this->calls++;
        $this->sentRestrictions = $restrictions;
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
        $legs = [];
        for ($i = 1; $i < count($points); $i++) {
            $legs[] = new RouteLeg($i - 1, $i, 100000.0, 2.5 * 3600, []); // 2,5 h per leg
        }

        return new RouteResult($legs, false);
    }

    public function testRouteSkipsStopsWithoutCoordinatesAndWarnsAboutTightTiming(): void
    {
        $route = $this->service()->forRental('tour');

        $this->assertSame(['start', 'stop', 'stop'], array_map(static fn ($p) => $p->kind, $route->points));
        $this->assertStringContainsString('Stop 3: Freitext-Ort: keine Koordinaten', implode(' ', $route->hints));
        // Teardown Hamburg 08:00, setup Kassel 11:00 → 180 min, needed 150 min + 30 min buffer = 180 → fine.
        $this->assertSame([], $route->timingWarnings);

        $this->settings->bufferMinutes = 45;
        $route = $this->service()->forRental('tour');
        $this->assertCount(1, $route->timingWarnings);
        $this->assertSame('s2', $route->timingWarnings[0]['stopId']);
        $this->assertStringContainsString('2 h 30 min Fahrzeit plus 45 min Puffer', $route->timingWarnings[0]['message']);
        $this->assertCount(1, $route->googleLinks);
    }

    public function testRouteIsCachedUntilTheInputChanges(): void
    {
        $this->service()->forRental('tour');
        $this->service()->forRental('tour');
        $this->assertSame(1, $this->calls);

        $this->tours->saveStop(new TourStop('wp', 'tour', TourStopKind::Waypoint, afterStopId: 's1', label: 'Hotel', latitude: 52.4, longitude: 9.7));
        $route = $this->service()->forRental('tour');
        $this->assertSame(2, $this->calls);
        $this->assertSame(['start', 'stop', 'waypoint', 'stop'], array_map(static fn ($p) => $p->kind, $route->points));
    }

    public function testWithoutApiKeyOrOnErrorStraightLinesAreUsed(): void
    {
        $this->settings->apiKey = null;
        $route = $this->service()->forRental('tour');
        $this->assertTrue($route->result?->straightLine);
        $this->assertSame(0, $this->calls);
        $this->assertSame([], $route->timingWarnings);

        $this->settings->apiKey = 'key';
        $this->failWith = new RoutingException('Kontingent erschöpft');
        $route = $this->service()->forRental('tour');
        $this->assertTrue($route->result?->straightLine);
        $this->assertStringContainsString('Kontingent erschöpft', implode(' ', $route->hints));
    }

    public function testTruckProfileUsesPlannedVehiclesAndReportsMissingDimensions(): void
    {
        $rental = $this->rentals->findById('tour');
        $rental->routeProfile = 'hgv_vehicles';
        $this->rentals->save($rental);
        $this->vehicles['lkw'] = RoutingDomainTest::vehicle('lkw', VehicleCategory::Auto, '340', null, '800', '7490');
        $this->planned['p1'] = new PlannedVehicle('p1', 'tour', 'lkw');

        $route = $this->service()->forRental('tour');

        $this->assertSame(3.4, $this->sentRestrictions?->heightM);
        $this->assertNull($this->sentRestrictions?->widthM);
        $this->assertStringContainsString('Breite von „Fahrzeug lkw“ fehlt', implode(' ', $route->hints));
    }

    public function testPlanningVehiclesAndSettings(): void
    {
        $this->vehicles['van'] = RoutingDomainTest::vehicle('van', VehicleCategory::Auto, '280', '220', '600', '3500');
        $this->vehicles['trailer'] = RoutingDomainTest::vehicle('trailer', VehicleCategory::Anhaenger, '250', '230', '450', '2000');
        $settings = $this->settingsRepository();
        $handler = new RoutingCommandHandler($settings, $this->rentals, $this->plannedRepository(), $this->vehicleRepository());

        $handler->handle(new AddPlannedVehicle('tour', 'trailer', 'van'));
        $this->assertSame(['trailer', 'van'], array_values(array_map(static fn (PlannedVehicle $p) => $p->vehicleId, $this->planned)));

        try {
            $handler->handle(new AddPlannedVehicle('tour', 'van', 'trailer'));
            $this->fail('ValidationException expected');
        } catch (ValidationException) {
        }

        $handler->handle(new SaveRoutingSettings('', '20'));
        $this->assertSame('key', $this->settings->apiKey);
        $this->assertSame(20, $this->settings->bufferMinutes);
        $handler->handle(new SaveRoutingSettings('', '20', true));
        $this->assertNull($this->settings->apiKey);
    }

    // --- fakes ----------------------------------------------------------

    private function settingsRepository(): RoutingSettingsRepositoryInterface
    {
        $test = $this;

        return new class ($test) implements RoutingSettingsRepositoryInterface {
            public function __construct(private readonly TourRouteServiceTest $test)
            {
            }

            public function get(): RoutingSettings
            {
                return $this->test->settings();
            }

            public function save(RoutingSettings $settings): void
            {
                $this->test->setSettings($settings);
            }
        };
    }

    public function settings(): RoutingSettings
    {
        return $this->settings;
    }

    public function setSettings(RoutingSettings $settings): void
    {
        $this->settings = $settings;
    }

    private function cacheRepository(): RouteCacheRepositoryInterface
    {
        $cache = &$this->cache;

        return new class ($cache) implements RouteCacheRepositoryInterface {
            /** @param array<string, array<string, mixed>> $cache */
            public function __construct(private array &$cache)
            {
            }

            public function find(string $rentalId, string $hash): ?array
            {
                return $this->cache[$rentalId . $hash] ?? null;
            }

            public function store(string $rentalId, string $hash, array $payload): void
            {
                $this->cache[$rentalId . $hash] = $payload;
            }
        };
    }

    private function plannedRepository(): PlannedVehicleRepositoryInterface
    {
        $planned = &$this->planned;

        return new class ($planned) implements PlannedVehicleRepositoryInterface {
            /** @param array<string, PlannedVehicle> $planned */
            public function __construct(private array &$planned)
            {
            }

            public function findByRental(string $rentalId): array
            {
                return array_values(array_filter($this->planned, static fn (PlannedVehicle $p) => $p->rentalId === $rentalId));
            }

            public function save(PlannedVehicle $vehicle): void
            {
                $this->planned[$vehicle->id] = $vehicle;
            }

            public function delete(string $id): void
            {
                unset($this->planned[$id]);
            }
        };
    }

    private function vehicleRepository(): VehicleRepositoryInterface
    {
        $vehicles = &$this->vehicles;

        return new class ($vehicles) implements VehicleRepositoryInterface {
            /** @param array<string, Vehicle> $vehicles */
            public function __construct(private array &$vehicles)
            {
            }

            public function save(Vehicle $vehicle): void
            {
                $this->vehicles[$vehicle->id] = $vehicle;
            }

            public function findById(string $id): ?Vehicle
            {
                return $this->vehicles[$id] ?? null;
            }

            public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
            {
                return array_values($this->vehicles);
            }

            public function findByCategory(VehicleCategory $category): array
            {
                return [];
            }

            public function findByIds(array $ids): array
            {
                return [];
            }

            public function countAll(?string $search = null): int
            {
                return count($this->vehicles);
            }

            public function isUsedByVehicleLoads(string $id): bool
            {
                return false;
            }

            public function isUsedByVehicleTows(string $id): bool
            {
                return false;
            }

            public function delete(string $id): void
            {
            }
        };
    }
}
