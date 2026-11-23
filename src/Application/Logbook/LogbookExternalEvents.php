<?php

declare(strict_types=1);

namespace App\Application\Logbook;

use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Vehicle\RentalVehicleLoadRepositoryInterface;
use DateTimeImmutable;

/**
 * Events an external driver may assign a trip to (migration 185): events
 * that overlap the trip date +/- one day and for which the vehicle is planned
 * (also as towing car) or loaded. Only name and date are shown to them.
 */
final class LogbookExternalEvents
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly PlannedVehicleRepositoryInterface $plannedVehicles,
        private readonly ?RentalVehicleLoadRepositoryInterface $loads = null,
    ) {
    }

    /** @return Rental[] sorted by start */
    public function forVehicle(string $vehicleId, DateTimeImmutable $date): array
    {
        $result = [];
        foreach ($this->rentals->findOverlapping($date->modify('-1 day')->setTime(0, 0), $date->modify('+1 day')->setTime(23, 59, 59)) as $rental) {
            if ($this->usesVehicle($rental->id, $vehicleId)) {
                $result[] = $rental;
            }
        }
        usort($result, static fn (Rental $a, Rental $b) => [$a->startDate, $a->eventName] <=> [$b->startDate, $b->eventName]);

        return $result;
    }

    public function allows(string $vehicleId, DateTimeImmutable $date, string $rentalId): bool
    {
        foreach ($this->forVehicle($vehicleId, $date) as $rental) {
            if ($rental->id === $rentalId) {
                return true;
            }
        }

        return false;
    }

    private function usesVehicle(string $rentalId, string $vehicleId): bool
    {
        foreach ($this->plannedVehicles->findByRental($rentalId) as $planned) {
            if ($planned->vehicleId === $vehicleId || $planned->towingVehicleId === $vehicleId) {
                return true;
            }
        }
        foreach ($this->loads?->findByRental($rentalId) ?? [] as $load) {
            if ($load->vehicleId === $vehicleId) {
                return true;
            }
        }

        return false;
    }
}
