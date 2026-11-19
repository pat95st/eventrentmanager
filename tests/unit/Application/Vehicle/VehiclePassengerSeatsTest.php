<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Vehicle;

use App\Application\Vehicle\Command\CreateVehicle;
use App\Application\Vehicle\Command\CreateVehicleHandler;
use App\Application\Vehicle\Command\UpdateVehicle;
use App\Application\Vehicle\Command\UpdateVehicleHandler;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use Codeception\Test\Unit;

/** Passenger seats of a car (0..8, excluding the driver); never stored for trailers. */
final class VehiclePassengerSeatsTest extends Unit
{
    public function testStoresSeatsForCar(): void
    {
        $this->assertSame(0, $this->create('auto', '0')->passengerSeats);
        $this->assertSame(8, $this->create('auto', ' 8 ')->passengerSeats);
    }

    public function testSeatsAreOptional(): void
    {
        $this->assertNull($this->create('auto', null)->passengerSeats);
        $this->assertNull($this->create('auto', '')->passengerSeats);
    }

    /** @dataProvider invalidSeats */
    public function testRejectsInvalidSeats(string $seats): void
    {
        try {
            $this->create('auto', $seats);
            $this->fail('ValidationException erwartet');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Die Anzahl der Beifahrerplätze muss zwischen 0 und 8 liegen.',
                $e->errors()['passengerSeats'] ?? null,
            );
        }
    }

    /** @return array<string, array{string}> */
    public static function invalidSeats(): array
    {
        return ['too many' => ['9'], 'negative' => ['-1'], 'decimal' => ['2.5'], 'text' => ['zwei']];
    }

    public function testTrailerSilentlyIgnoresSeats(): void
    {
        $this->assertNull($this->create('anhaenger', '3')->passengerSeats);
        // Even an invalid value is ignored for trailers instead of producing an error.
        $this->assertNull($this->create('anhaenger', '99')->passengerSeats);
    }

    public function testSwitchingCarToTrailerClearsSeats(): void
    {
        $vehicle = $this->create('auto', '2');
        $repository = $this->repository($saved, $vehicle);

        (new UpdateVehicleHandler($repository))->handle(new UpdateVehicle(
            id: $vehicle->id,
            category: 'anhaenger',
            name: 'Sprinter',
            licensePlate: null,
            payloadKg: null,
            loadAreaLengthCm: null,
            loadAreaWidthCm: null,
            loadAreaHeightCm: null,
            loadAreaOpenTop: false,
            towHitchAvailable: false,
            towCapacityKg: null,
            emptyWeightKg: null,
            passengerSeats: '2',
        ));

        $this->assertInstanceOf(Vehicle::class, $saved);
        $this->assertNull($saved->passengerSeats);
    }

    private function create(string $category, ?string $seats): Vehicle
    {
        $repository = $this->repository($saved);

        (new CreateVehicleHandler($repository))->handle(new CreateVehicle(
            category: $category,
            name: 'Sprinter',
            licensePlate: null,
            payloadKg: null,
            loadAreaLengthCm: null,
            loadAreaWidthCm: null,
            loadAreaHeightCm: null,
            loadAreaOpenTop: false,
            towHitchAvailable: false,
            towCapacityKg: null,
            emptyWeightKg: null,
            passengerSeats: $seats,
        ));
        $this->assertInstanceOf(Vehicle::class, $saved);

        return $saved;
    }

    private function repository(?Vehicle &$saved, ?Vehicle $existing = null): VehicleRepositoryInterface
    {
        $saved = null;
        $repository = $this->createMock(VehicleRepositoryInterface::class);
        $repository->method('findById')->willReturn($existing);
        $repository->method('save')->willReturnCallback(static function (Vehicle $vehicle) use (&$saved): void {
            $saved = $vehicle;
        });

        return $repository;
    }
}
