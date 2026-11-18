<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\RentalSeries\RentalChildDataCopierInterface;

/** Records which child data would have been copied; planned material is kept per rental for assertions. */
final class FakeRentalChildDataCopier implements RentalChildDataCopierInterface
{
    /** @var list<array{0: string, 1: string, 2: string, 3?: int|bool}> */
    public array $calls = [];

    /** @var array<string, array<string, int>> rental id => article id => quantity */
    public array $material = [];

    public function replaceMaterial(string $sourceRentalId, string $targetRentalId): void
    {
        $this->calls[] = ['material', $sourceRentalId, $targetRentalId];
        $this->material[$targetRentalId] = $this->material[$sourceRentalId] ?? [];
    }

    public function replaceMinutePrograms(string $sourceRentalId, string $targetRentalId, int $dayOffset): void
    {
        $this->calls[] = ['minute_programs', $sourceRentalId, $targetRentalId, $dayOffset];
    }

    public function shiftMinutePrograms(string $rentalId, int $dayOffset): void
    {
        $this->calls[] = ['shift_minute_programs', $rentalId, '', $dayOffset];
    }

    public function replacePowerPlan(string $sourceRentalId, string $targetRentalId, bool $keepVenueConnections): void
    {
        $this->calls[] = ['power_plan', $sourceRentalId, $targetRentalId, $keepVenueConnections];
    }

    public function replaceTourStops(string $sourceRentalId, string $targetRentalId, int $dayOffset): void
    {
        $this->calls[] = ['tour_stops', $sourceRentalId, $targetRentalId, $dayOffset];
    }

    public function shiftTourStops(string $rentalId, int $dayOffset): void
    {
        $this->calls[] = ['shift_tour_stops', $rentalId, '', $dayOffset];
    }

    public function replaceTrades(string $sourceRentalId, string $targetRentalId): void
    {
        $this->calls[] = ['trades', $sourceRentalId, $targetRentalId];
    }

    /** @return list<string> kinds of the calls targeting $rentalId */
    public function kindsFor(string $rentalId): array
    {
        return array_values(array_map(
            static fn (array $c) => $c[0],
            array_filter($this->calls, static fn (array $c) => $c[2] === $rentalId || ($c[0] === 'shift_minute_programs' && $c[1] === $rentalId)),
        ));
    }
}
