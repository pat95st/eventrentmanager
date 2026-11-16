<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Vehicle;

use App\Application\Vehicle\Command\CreateVehicle;
use App\Application\Vehicle\Command\CreateVehicleHandler;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use Codeception\Test\Unit;

/** Insurance values of a vehicle (acquisition year, purchase price, current value). */
final class VehicleInsuranceValuesTest extends Unit
{
    public function testParsesGermanAmountsAndYear(): void
    {
        $saved = $this->create('2021', '38.500,00', '24.000');

        $this->assertSame(2021, $saved->acquisitionYear);
        $this->assertSame('38500.00', $saved->purchasePrice);
        $this->assertSame('24000.00', $saved->currentValue);
    }

    public function testValuesAreOptional(): void
    {
        $saved = $this->create(null, '', null);

        $this->assertNull($saved->acquisitionYear);
        $this->assertNull($saved->purchasePrice);
        $this->assertNull($saved->currentValue);
    }

    public function testRejectsInvalidValues(): void
    {
        try {
            $this->create('21', 'abc', '-5');
            $this->fail('ValidationException erwartet');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('acquisitionYear', $e->errors());
            $this->assertArrayHasKey('purchasePrice', $e->errors());
            $this->assertArrayHasKey('currentValue', $e->errors());
        }
    }

    private function create(?string $year, ?string $purchasePrice, ?string $currentValue): Vehicle
    {
        $repository = $this->createMock(VehicleRepositoryInterface::class);
        $saved = null;
        $repository->method('save')->willReturnCallback(static function (Vehicle $vehicle) use (&$saved): void {
            $saved = $vehicle;
        });

        (new CreateVehicleHandler($repository))->handle(new CreateVehicle(
            category: 'auto',
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
            acquisitionYear: $year,
            purchasePrice: $purchasePrice,
            currentValue: $currentValue,
        ));
        $this->assertInstanceOf(Vehicle::class, $saved);

        return $saved;
    }
}
