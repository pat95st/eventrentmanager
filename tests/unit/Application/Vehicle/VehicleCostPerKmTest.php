<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Vehicle;

use App\Application\Vehicle\Command\CreateVehicle;
use App\Application\Vehicle\Command\CreateVehicleHandler;
use App\Application\Vehicle\Command\SaveVehicleSettings;
use App\Application\Vehicle\Command\SaveVehicleSettingsHandler;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleSettings;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use Codeception\Test\Unit;

/** Cost per km of vehicles and trailers with defaults from the settings (migration 181). */
final class VehicleCostPerKmTest extends Unit
{
    public function testDefaultsAreThirtyCentForCarsAndFifteenForTrailers(): void
    {
        $settings = new VehicleSettings();

        $this->assertSame('0.30', $settings->defaultCostPerKmAuto);
        $this->assertSame('0.15', $settings->defaultCostPerKmTrailer);
    }

    public function testEmptyValueUsesDefaultOfCategory(): void
    {
        $settings = new VehicleSettings('0.35', '0.12');

        $car = $this->create('auto', '');
        $trailer = $this->create('anhaenger', null);

        $this->assertNull($car->costPerKm);
        $this->assertTrue($car->usesDefaultCostPerKm());
        $this->assertSame('0.35', $car->effectiveCostPerKm($settings));
        $this->assertSame('0.12', $trailer->effectiveCostPerKm($settings));
    }

    public function testIndividualValueOverridesDefault(): void
    {
        $trailer = $this->create('anhaenger', '0,20 €');

        $this->assertSame('0.20', $trailer->costPerKm);
        $this->assertFalse($trailer->usesDefaultCostPerKm());
        $this->assertSame('0.20', $trailer->effectiveCostPerKm(new VehicleSettings()));
    }

    public function testRejectsInvalidCostPerKm(): void
    {
        try {
            $this->create('auto', 'abc');
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('costPerKm', $e->errors());
        }
    }

    public function testSavesDefaultsInSettings(): void
    {
        $stored = new VehicleSettings();
        $repository = $this->createMock(VehicleSettingsRepositoryInterface::class);
        $repository->method('get')->willReturn($stored);
        $repository->expects($this->once())->method('save');

        (new SaveVehicleSettingsHandler($repository))->handle(new SaveVehicleSettings('0,42', '0.18'));

        $this->assertSame('0.42', $stored->defaultCostPerKmAuto);
        $this->assertSame('0.18', $stored->defaultCostPerKmTrailer);
    }

    public function testSettingsRequireBothDefaults(): void
    {
        $repository = $this->createMock(VehicleSettingsRepositoryInterface::class);
        $repository->method('get')->willReturn(new VehicleSettings());
        $repository->expects($this->never())->method('save');

        try {
            (new SaveVehicleSettingsHandler($repository))->handle(new SaveVehicleSettings('', '-1'));
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('defaultCostPerKmAuto', $e->errors());
            $this->assertArrayHasKey('defaultCostPerKmTrailer', $e->errors());
        }
    }

    private function create(string $category, ?string $costPerKm): Vehicle
    {
        $repository = $this->createMock(VehicleRepositoryInterface::class);
        $saved = null;
        $repository->method('save')->willReturnCallback(static function (Vehicle $vehicle) use (&$saved): void {
            $saved = $vehicle;
        });

        (new CreateVehicleHandler($repository))->handle(new CreateVehicle(
            category: $category,
            name: 'Fahrzeug',
            licensePlate: null,
            payloadKg: null,
            loadAreaLengthCm: null,
            loadAreaWidthCm: null,
            loadAreaHeightCm: null,
            loadAreaOpenTop: false,
            towHitchAvailable: false,
            towCapacityKg: null,
            emptyWeightKg: null,
            costPerKm: $costPerKm,
        ));
        $this->assertInstanceOf(Vehicle::class, $saved);

        return $saved;
    }
}
