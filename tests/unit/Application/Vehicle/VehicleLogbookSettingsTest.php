<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Vehicle;

use App\Application\Vehicle\Command\CreateVehicle;
use App\Application\Vehicle\Command\CreateVehicleHandler;
use App\Application\Vehicle\Command\SaveVehicleLogbookSettings;
use App\Application\Vehicle\Command\SaveVehicleLogbookSettingsHandler;
use App\Application\Vehicle\Command\UpdateVehicle;
use App\Application\Vehicle\Command\UpdateVehicleHandler;
use App\Domain\Exception\ValidationException;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;

/** Private vehicles and per-vehicle logbook settings (migration 183). */
final class VehicleLogbookSettingsTest extends Unit
{
    public function testLogbookPermissionsExist(): void
    {
        $this->assertContains(Permission::Fahrer, Permission::all());
        $this->assertContains(Permission::FahrtenbuchVerwalten, Permission::all());
        $this->assertSame('Fahrtenbuch verwalten', Permission::FahrtenbuchVerwalten->label());
    }

    public function testPrivateVehicleKeepsOwner(): void
    {
        $vehicle = $this->create(true, 'u1', [$this->user('u1', true)]);

        $this->assertTrue($vehicle->isPrivate);
        $this->assertSame('u1', $vehicle->ownerUserId);
        $this->assertTrue($vehicle->isOwnedBy('u1'));
        $this->assertFalse($vehicle->isOwnedBy('u2'));
    }

    public function testOwnerIsDroppedWithoutPrivateFlag(): void
    {
        $vehicle = $this->create(false, 'u1', [$this->user('u1', true)]);

        $this->assertFalse($vehicle->isPrivate);
        $this->assertNull($vehicle->ownerUserId);
        $this->assertFalse($vehicle->isOwnedBy('u1'));
    }

    public function testPrivateVehicleRequiresOwner(): void
    {
        $this->expectValidationError('ownerUserId', fn () => $this->create(true, null, []));
    }

    public function testNewOwnerMustBeActive(): void
    {
        $this->expectValidationError('ownerUserId', fn () => $this->create(true, 'u1', [$this->user('u1', false)]));
        $this->expectValidationError('ownerUserId', fn () => $this->create(true, 'unknown', []));
    }

    public function testInactiveOwnerAlreadyAssignedMayStay(): void
    {
        $vehicle = $this->vehicle();
        $vehicle->isPrivate = true;
        $vehicle->ownerUserId = 'u1';
        $repository = $this->vehicleRepository($vehicle);

        (new UpdateVehicleHandler($repository, $this->users([$this->user('u1', false)])))->handle(new UpdateVehicle(
            id: $vehicle->id,
            category: 'auto',
            name: 'Golf',
            licensePlate: null,
            payloadKg: null,
            loadAreaLengthCm: null,
            loadAreaWidthCm: null,
            loadAreaHeightCm: null,
            loadAreaOpenTop: false,
            towHitchAvailable: false,
            towCapacityKg: null,
            emptyWeightKg: null,
            isPrivate: true,
            ownerUserId: 'u1',
        ));

        $this->assertSame('u1', $vehicle->ownerUserId);
    }

    public function testSavesLogbookSettings(): void
    {
        $vehicle = $this->vehicle();

        (new SaveVehicleLogbookSettingsHandler($this->vehicleRepository($vehicle)))->handle(
            new SaveVehicleLogbookSettings($vehicle->id, true, true, true, true, true, '5', '48.250')
        );

        $this->assertTrue($vehicle->logbookQrEnabled);
        $this->assertTrue($vehicle->logbookAnonymousAllowed);
        $this->assertTrue($vehicle->logbookAnonymousPrivateAllowed);
        $this->assertTrue($vehicle->logbookAnonymousRequiresApproval);
        $this->assertTrue($vehicle->logbookOdometerCheck);
        $this->assertSame(5, $vehicle->logbookOdometerToleranceKm);
        $this->assertSame(48250, $vehicle->logbookOdometerInitialKm);
    }

    public function testEmptyToleranceMeansExactMatch(): void
    {
        $vehicle = $this->vehicle();
        $vehicle->logbookOdometerToleranceKm = 3;

        (new SaveVehicleLogbookSettingsHandler($this->vehicleRepository($vehicle)))->handle(
            new SaveVehicleLogbookSettings($vehicle->id, false, false, false, false, false, '', null)
        );

        $this->assertSame(0, $vehicle->logbookOdometerToleranceKm);
        $this->assertNull($vehicle->logbookOdometerInitialKm);
    }

    public function testOdometerCheckRequiresInitialReading(): void
    {
        $vehicle = $this->vehicle();
        $handler = new SaveVehicleLogbookSettingsHandler($this->vehicleRepository($vehicle, false));

        $this->expectValidationError('odometerInitialKm', fn () => $handler->handle(
            new SaveVehicleLogbookSettings($vehicle->id, true, false, false, false, true, '0', null)
        ));
        $this->expectValidationError('odometerToleranceKm', fn () => $handler->handle(
            new SaveVehicleLogbookSettings($vehicle->id, true, false, false, false, false, '-2', null)
        ));
        $this->assertFalse($vehicle->logbookOdometerCheck);
    }

    public function testVehicleWithLogbookTripsCannotBeDeleted(): void
    {
        $vehicle = $this->vehicle();
        $repository = $this->createMock(VehicleRepositoryInterface::class);
        $repository->method('findById')->willReturn($vehicle);
        $repository->expects($this->never())->method('delete');
        $logbook = new \Tests\Support\Fakes\InMemoryLogbookEntryRepository();
        $now = new DateTimeImmutable();
        $logbook->save(new \App\Domain\Logbook\LogbookEntry('e1', $now, null, null, 'u1', null, null, false, $vehicle->id, null, null, \App\Domain\Logbook\LogbookCategory::Private, null, null, null, null, null, false, 10, null, null, false, \App\Domain\Logbook\LogbookStatus::Approved, false, 'u1', $now, $now));

        $this->expectException(\App\Domain\Exception\ConflictException::class);
        (new \App\Application\Vehicle\Command\DeleteVehicleHandler($repository, $logbook))->handle(new \App\Application\Vehicle\Command\DeleteVehicle($vehicle->id));
    }

    /** @param User[] $users */
    private function create(bool $isPrivate, ?string $ownerUserId, array $users): Vehicle
    {
        $repository = $this->createMock(VehicleRepositoryInterface::class);
        $saved = null;
        $repository->method('save')->willReturnCallback(static function (Vehicle $vehicle) use (&$saved): void {
            $saved = $vehicle;
        });

        (new CreateVehicleHandler($repository, $this->users($users)))->handle(new CreateVehicle(
            category: 'auto',
            name: 'Golf',
            licensePlate: null,
            payloadKg: null,
            loadAreaLengthCm: null,
            loadAreaWidthCm: null,
            loadAreaHeightCm: null,
            loadAreaOpenTop: false,
            towHitchAvailable: false,
            towCapacityKg: null,
            emptyWeightKg: null,
            isPrivate: $isPrivate,
            ownerUserId: $ownerUserId,
        ));
        $this->assertInstanceOf(Vehicle::class, $saved);

        return $saved;
    }

    private function expectValidationError(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    private function vehicle(): Vehicle
    {
        $now = new DateTimeImmutable();

        return new Vehicle('v1', VehicleCategory::Auto, 'Golf', null, null, null, null, null, false, false, null, null, null, $now, $now);
    }

    private function vehicleRepository(Vehicle $vehicle, bool $expectSave = true): VehicleRepositoryInterface
    {
        $repository = $this->createMock(VehicleRepositoryInterface::class);
        $repository->method('findById')->willReturn($vehicle);
        $repository->expects($expectSave ? $this->once() : $this->never())->method('save');

        return $repository;
    }

    /** @param User[] $users */
    private function users(array $users): UserRepositoryInterface
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(static function (string $id) use ($users): ?User {
            foreach ($users as $user) {
                if ($user->id === $id) {
                    return $user;
                }
            }

            return null;
        });

        return $repository;
    }

    private function user(string $id, bool $active): User
    {
        $now = new DateTimeImmutable();

        return new User($id, $id . '@example.org', null, 'Max', 'Muster', [Permission::Fahrer], TwoFactorMethod::None, null, false, false, $active, $now, $now);
    }
}
