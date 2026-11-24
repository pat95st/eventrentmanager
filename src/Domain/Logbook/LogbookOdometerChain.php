<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

use App\Domain\Vehicle\Vehicle;

/**
 * Odometer check of a vehicle (migrations 183/185): the current reading must
 * match the last recorded reading (or the initial reading of the vehicle)
 * within the tolerance of the vehicle.
 */
final class LogbookOdometerChain
{
    public function __construct(private readonly LogbookEntryRepositoryInterface $entries)
    {
    }

    /** Reading the next trip has to start with; null = the vehicle has no reading yet. */
    public function expected(Vehicle $vehicle): ?int
    {
        return $this->entries->lastOdometerReading($vehicle->id) ?? $vehicle->logbookOdometerInitialKm;
    }

    public function matches(Vehicle $vehicle, int $reading): bool
    {
        $expected = $this->expected($vehicle);

        return $expected === null || abs($reading - $expected) <= $vehicle->logbookOdometerToleranceKm;
    }

    /** The reading lies beyond the tolerance above the expected one: trips are missing in between. */
    public function isGap(Vehicle $vehicle, int $reading): bool
    {
        $expected = $this->expected($vehicle);

        return $expected !== null && $reading - $expected > $vehicle->logbookOdometerToleranceKm;
    }
}
