<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Logbook;

use App\Application\Logbook\Command\DeleteLogbookEntry;
use App\Application\Logbook\Command\LogbookEntryLifecycleHandler;
use App\Application\Logbook\Command\RestoreLogbookEntry;
use App\Application\Logbook\Command\SaveLogbookEntry;
use App\Application\Logbook\Command\SaveLogbookEntryHandler;
use App\Application\Logbook\Command\SaveLogbookSettings;
use App\Application\Logbook\Command\SaveLogbookSettingsHandler;
use App\Application\Logbook\LogbookLocationResolver;
use App\Domain\Exception\ForbiddenException;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookEntryChange;
use App\Domain\Logbook\LogbookLocationType;
use App\Domain\Logbook\LogbookSettings;
use App\Domain\Rental\Rental;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Domain\Warehouse\Warehouse;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\FixedLogbookSettingsRepository;
use Tests\Support\Fakes\InMemoryLogbookEntryRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;
use Tests\Support\Fakes\InMemoryUserRepository;

/** Recording, changing and deleting trips of the driver's logbook (migration 184). */
final class SaveLogbookEntryTest extends Unit
{
    private InMemoryLogbookEntryRepository $entries;
    private FixedLogbookSettingsRepository $settings;
    private InMemoryUserRepository $users;
    /** @var array<string, Vehicle> */
    private array $vehicles = [];
    private SaveLogbookEntryHandler $handler;

    protected function _before(): void
    {
        $this->entries = new InMemoryLogbookEntryRepository();
        $this->settings = new FixedLogbookSettingsRepository();
        $this->users = new InMemoryUserRepository();
        $this->users->save($this->user('driver', [Permission::Fahrer]));
        $this->users->save($this->user('other', [Permission::Fahrer]));
        $this->users->save($this->user('manager', [Permission::FahrtenbuchVerwalten]));
        $this->users->save($this->user('nobody', [Permission::Kundenverwaltung]));

        $this->vehicles['car'] = $this->vehicle('car', VehicleCategory::Auto);
        $this->vehicles['trailer'] = $this->vehicle('trailer', VehicleCategory::Anhaenger);
        $private = $this->vehicle('private', VehicleCategory::Auto);
        $private->isPrivate = true;
        $private->ownerUserId = 'other';
        $this->vehicles['private'] = $private;
        $checked = $this->vehicle('checked', VehicleCategory::Auto);
        $checked->logbookOdometerCheck = true;
        $checked->logbookOdometerInitialKm = 1000;
        $this->vehicles['checked'] = $checked;

        $vehicles = $this->createMock(VehicleRepositoryInterface::class);
        $vehicles->method('findById')->willReturnCallback(fn (string $id) => $this->vehicles[$id] ?? null);

        $rentals = new InMemoryRentalRepository();
        $now = new DateTimeImmutable();
        $rentals->save(new Rental('rental-1', 'customer-1', new DateTimeImmutable('-1 day'), new DateTimeImmutable('+1 day'), 'Stadtfest', 'Marktplatz Musterstadt', null, 3, $now, $now, pickupWarehouseId: 'wh-1'));

        $warehouses = $this->createMock(WarehouseRepositoryInterface::class);
        $warehouses->method('findById')->willReturnCallback(static fn (string $id) => $id === 'wh-1'
            ? new Warehouse('wh-1', 'Hauptlager', 'Lagerweg 1', '90000', 'Musterstadt', null, null, 49.4, 11.0, $now, $now)
            : null);
        $tours = $this->createMock(TourRepositoryInterface::class);
        $resolver = new LogbookLocationResolver($warehouses, $this->createMock(VenueRepositoryInterface::class), $rentals, $tours);

        $this->handler = new SaveLogbookEntryHandler($this->entries, $this->settings, $this->users, $vehicles, $rentals, $tours, $resolver);
    }

    public function testDriverRecordsEventTrip(): void
    {
        $id = $this->save([]);

        $entry = $this->entries->findById($id);
        $this->assertNotNull($entry);
        $this->assertSame('driver', $entry->driverUserId);
        $this->assertSame('driver', $entry->createdByUserId);
        $this->assertSame(LogbookCategory::Event, $entry->category);
        $this->assertSame('rental-1', $entry->rentalId);
        $this->assertSame(LogbookLocationType::Warehouse, $entry->from?->type);
        $this->assertSame('Hauptlager', $entry->from?->label);
        $this->assertSame(LogbookLocationType::EventVenue, $entry->to?->type);
        $this->assertSame('Marktplatz Musterstadt', $entry->to?->label);
        $this->assertSame(42, $entry->distanceKm);
        $this->assertTrue($entry->isBillable());
        $this->assertCount(1, $this->entries->changes);
        $this->assertSame(LogbookEntryChange::CREATED, $this->entries->changes[0]->action);
    }

    public function testUsersWithoutLogbookRightCannotRecord(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->save(['actorUserId' => 'nobody']);
    }

    public function testRequiredFieldsPerCategory(): void
    {
        $this->assertErrors(['rentalId'], ['rentalId' => null]);
        $this->assertErrors(['purpose', 'from', 'to'], ['category' => 'business', 'from' => null, 'to' => null]);
        $this->assertErrors(['distanceKm'], ['distanceKm' => '']);
        $this->assertErrors(['vehicleId'], ['vehicleId' => 'trailer']);
        $this->assertErrors(['otherVehicleLabel'], ['vehicleId' => 'other']);
        $this->assertErrors(['trailerId'], ['trailerId' => 'car']);
        $this->assertErrors(['tripDate'], ['tripDate' => (new DateTimeImmutable('+3 days'))->format('Y-m-d')]);
    }

    public function testPrivateTripNeedsNoLocations(): void
    {
        $id = $this->save(['category' => 'private', 'from' => null, 'to' => null, 'rentalId' => 'rental-1']);

        $entry = $this->entries->findById($id);
        $this->assertNull($entry?->from);
        $this->assertNull($entry?->rentalId);
        $this->assertFalse($entry?->isBillable());
    }

    public function testCustomAddressAndOtherVehicle(): void
    {
        $id = $this->save(['category' => 'business', 'purpose' => 'Einkauf', 'to' => 'custom:', 'toText' => 'Baumarkt Musterstadt', 'vehicleId' => 'other', 'otherVehicleLabel' => 'Privat-PKW']);

        $entry = $this->entries->findById($id);
        $this->assertSame(LogbookLocationType::Custom, $entry?->to?->type);
        $this->assertSame('Baumarkt Musterstadt', $entry?->to?->label);
        $this->assertNull($entry?->vehicleId);
        $this->assertSame('Privat-PKW', $entry?->otherVehicleLabel);
    }

    public function testOdometerReadingsDefineTheDistance(): void
    {
        $id = $this->save(['odometerStart' => '12.300', 'odometerEnd' => '12.388', 'distanceKm' => '5']);
        $this->assertSame(88, $this->entries->findById($id)?->distanceKm);

        $this->assertErrors(['odometerEnd'], ['odometerStart' => '500', 'odometerEnd' => '400']);
        $this->assertErrors(['odometerEnd'], ['odometerStart' => '500', 'odometerEnd' => '']);
    }

    public function testVehicleWithOdometerCheckRequiresReadings(): void
    {
        $this->assertErrors(['odometerStart', 'odometerEnd'], ['vehicleId' => 'checked']);
        $id = $this->save(['vehicleId' => 'checked', 'odometerStart' => '1000', 'odometerEnd' => '1042']);
        $this->assertSame(42, $this->entries->findById($id)?->distanceKm);
    }

    public function testRoundTripDoublesTheOneWayDistance(): void
    {
        $id = $this->save(['roundTrip' => true, 'distanceKm' => '21']);

        $entry = $this->entries->findById($id);
        $this->assertTrue($entry?->roundTrip);
        $this->assertSame(42, $entry?->distanceKm);
    }

    public function testPrivateVehicleOnlyForOwnerAndManagers(): void
    {
        $this->assertErrors(['vehicleId'], ['vehicleId' => 'private']);
        $this->assertNotSame('', $this->save(['vehicleId' => 'private', 'actorUserId' => 'other']));
        $this->assertNotSame('', $this->save(['vehicleId' => 'private', 'actorUserId' => 'manager']));
    }

    public function testOnlyManagersSetDriverAndNotBilled(): void
    {
        $id = $this->save(['driverUserId' => 'other', 'notBilled' => true]);
        $entry = $this->entries->findById($id);
        $this->assertSame('driver', $entry?->driverUserId);
        $this->assertFalse($entry?->notBilled);

        $id = $this->save(['actorUserId' => 'manager', 'driverUserId' => 'external', 'driverName' => 'Erika Extern', 'notBilled' => true]);
        $entry = $this->entries->findById($id);
        $this->assertNull($entry?->driverUserId);
        $this->assertSame('Erika Extern', $entry?->driverName);
        $this->assertTrue($entry?->isExternal);
        $this->assertTrue($entry?->notBilled);
        $this->assertFalse($entry?->isBillable());
    }

    public function testDriverEditsOwnTripWithinWindowWithAuditTrail(): void
    {
        $id = $this->save([]);
        $this->save(['entryId' => $id, 'distanceKm' => '45', 'purpose' => 'Aufbau']);

        $entry = $this->entries->findById($id);
        $this->assertSame(45, $entry?->distanceKm);
        $this->assertCount(2, $this->entries->changes);
        $this->assertSame(LogbookEntryChange::UPDATED, $this->entries->changes[1]->action);
        $this->assertEquals(['distanceKm' => [42, 45], 'purpose' => [null, 'Aufbau']], $this->entries->changes[1]->changes);

        // Saving unchanged values writes no audit entry.
        $this->save(['entryId' => $id, 'distanceKm' => '45', 'purpose' => 'Aufbau']);
        $this->assertCount(2, $this->entries->changes);
    }

    public function testDriversCannotEditOthersOrExpiredTrips(): void
    {
        $id = $this->save([]);
        try {
            $this->save(['entryId' => $id, 'actorUserId' => 'other']);
            $this->fail('ForbiddenException expected');
        } catch (ForbiddenException) {
        }

        $this->settings->settings = new LogbookSettings(0);
        $stored = $this->entries->entries[$id];
        $this->entries->entries[$id] = new \App\Domain\Logbook\LogbookEntry(...array_merge(get_object_vars($stored), ['createdAt' => new DateTimeImmutable('-1 hour')]));
        try {
            $this->save(['entryId' => $id]);
            $this->fail('ForbiddenException expected');
        } catch (ForbiddenException) {
        }

        // Managers always can.
        $this->save(['entryId' => $id, 'actorUserId' => 'manager', 'distanceKm' => '50']);
        $this->assertSame(50, $this->entries->findById($id)?->distanceKm);
    }

    public function testDeleteAndRestore(): void
    {
        $id = $this->save([]);
        $lifecycle = new LogbookEntryLifecycleHandler($this->entries, $this->settings, $this->users);

        try {
            $lifecycle->handle(new DeleteLogbookEntry($id, 'other'));
            $this->fail('ForbiddenException expected');
        } catch (ForbiddenException) {
        }
        $lifecycle->handle(new DeleteLogbookEntry($id, 'driver'));
        $this->assertTrue($this->entries->findById($id)?->isDeleted());

        try {
            $lifecycle->handle(new RestoreLogbookEntry($id, 'driver'));
            $this->fail('ForbiddenException expected');
        } catch (ForbiddenException) {
        }
        $lifecycle->handle(new RestoreLogbookEntry($id, 'manager'));
        $this->assertFalse($this->entries->findById($id)?->isDeleted());
        $this->assertSame([LogbookEntryChange::CREATED, LogbookEntryChange::DELETED, LogbookEntryChange::RESTORED], array_map(static fn ($c) => $c->action, $this->entries->changes));
    }

    public function testEditWindowSetting(): void
    {
        $handler = new SaveLogbookSettingsHandler($this->settings);
        $handler->handle(new SaveLogbookSettings('14'));
        $this->assertSame(14, $this->settings->settings->editWindowDays);

        $this->expectException(ValidationException::class);
        $handler->handle(new SaveLogbookSettings('400'));
    }

    /** @param array<string, mixed> $overrides */
    private function save(array $overrides): string
    {
        $values = array_merge([
            'entryId' => null,
            'actorUserId' => 'driver',
            'tripDate' => (new DateTimeImmutable())->format('Y-m-d'),
            'vehicleId' => 'car',
            'category' => 'event',
            'rentalId' => 'rental-1',
            'from' => 'warehouse:wh-1',
            'to' => 'event_venue:rental-1',
            'distanceKm' => '42',
        ], $overrides);

        return $this->handler->handle(new SaveLogbookEntry(...$values));
    }

    /**
     * @param list<string> $fields
     * @param array<string, mixed> $overrides
     */
    private function assertErrors(array $fields, array $overrides): void
    {
        try {
            $this->save($overrides);
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }
    }

    /** @param Permission[] $permissions */
    private function user(string $id, array $permissions): User
    {
        $now = new DateTimeImmutable();

        return new User($id, $id . '@example.org', null, ucfirst($id), 'Test', $permissions, TwoFactorMethod::None, null, false, false, true, $now, $now);
    }

    private function vehicle(string $id, VehicleCategory $category): Vehicle
    {
        $now = new DateTimeImmutable();

        return new Vehicle($id, $category, ucfirst($id), null, null, null, null, null, false, false, null, null, null, $now, $now);
    }
}
