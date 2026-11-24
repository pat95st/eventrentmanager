<?php

declare(strict_types=1);

namespace App\Application\TravelCost;

use App\Domain\Logbook\LogbookEntry;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookFilter;
use App\Domain\Rental\Rental;
use App\Domain\TravelCost\TravelCostBasis;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostTrip;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;

/**
 * Travel costs from the kilometres actually driven (migration 187): the
 * billable trips of the event in the driver's logbook (category event,
 * approved, not deleted, not marked "nicht abrechnen"). Every trip is
 * billed with the cost per km of its own vehicle plus its trailer (an
 * "other vehicle" counts with the default for cars) plus the markup,
 * rounded to whole cents. Two vehicles driving count as two trips.
 */
final class ActualTravelCostCalculator
{
    public function __construct(
        private readonly LogbookEntryRepositoryInterface $entries,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly VehicleSettingsRepositoryInterface $vehicleSettings,
    ) {
    }

    public function forRental(Rental $rental): TourTravelCostProposal
    {
        $settings = $this->vehicleSettings->get();
        $billable = array_values(array_filter(
            $this->entries->search(new LogbookFilter(rentalId: $rental->id)),
            static fn (LogbookEntry $e) => $e->isBillable(),
        ));
        usort($billable, static fn (LogbookEntry $a, LogbookEntry $b) => [$a->tripDate, (string) $a->departTime, $a->createdAt] <=> [$b->tripDate, (string) $b->departTime, $b->createdAt]);

        $trips = [];
        $vehicles = [];
        $fingerprint = [];
        foreach ($billable as $entry) {
            $vehicle = $entry->vehicleId !== null ? $this->vehicles->findById($entry->vehicleId) : null;
            $trailer = $entry->trailerId !== null ? $this->vehicles->findById($entry->trailerId) : null;
            $cost = (float) ($vehicle?->effectiveCostPerKm($settings) ?? $settings->defaultCostPerKm(VehicleCategory::Auto));
            $label = $vehicle?->name ?? ($entry->otherVehicleLabel ?? 'Sonstiges Fahrzeug');
            if ($trailer !== null) {
                $cost += (float) $trailer->effectiveCostPerKm($settings);
                $label .= ' mit ' . $trailer->name;
            }
            $rate = $settings->billedRatePerKm($cost);
            $vehicles[$label] ??= ['name' => $label, 'costPerKm' => number_format($cost, 2, '.', ''), 'isDefault' => $vehicle === null || $vehicle->usesDefaultCostPerKm()];
            $trips[] = new TravelCostTrip(
                $entry->tripDate,
                $entry->from?->label ?? 'Start',
                ($entry->to?->label ?? 'Ziel') . ($entry->roundTrip ? ' und zurück' : ''),
                $entry->distanceKm,
                $rate,
                $label,
            );
            $fingerprint[] = [$entry->id, $entry->distanceKm, $rate, $entry->updatedAt->format('c')];
        }

        $first = $vehicles !== [] ? reset($vehicles) : null;

        return new TourTravelCostProposal(
            rentalId: $rental->id,
            enabled: $rental->travelCostsEnabled,
            mode: TravelCostMode::tryFrom($rental->travelCostsMode) ?? TravelCostMode::Summary,
            trips: $trips,
            vehicles: array_values($vehicles),
            costPerKm: $first['costPerKm'] ?? '0.00',
            markupPercent: $settings->travelCostMarkupPercent,
            ratePerKm: $trips[0]->ratePerKm ?? '0.00',
            hints: $trips === [] ? ['Im Fahrtenbuch gibt es keine abrechenbaren Fahrten zu dieser Veranstaltung.'] : [],
            basis: TravelCostBasis::Actual,
            sourceHash: self::fingerprint($fingerprint),
        );
    }

    /** Fingerprint of the billable logbook trips (to notice changes after an import). */
    public function fingerprintFor(Rental $rental): string
    {
        return (string) $this->forRental($rental)->sourceHash;
    }

    /** @param list<array<mixed>> $parts */
    private static function fingerprint(array $parts): string
    {
        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }
}
