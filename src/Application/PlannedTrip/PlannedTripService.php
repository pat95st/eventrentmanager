<?php

declare(strict_types=1);

namespace App\Application\PlannedTrip;

use App\Application\Logbook\LogbookDistanceService;
use App\Application\Logbook\LogbookLocationResolver;
use App\Application\TravelCost\TourTravelCostProposal;
use App\Application\TravelCost\TravelCostRate;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookLocation;
use App\Domain\PlannedTrip\PlannedTrip;
use App\Domain\PlannedTrip\PlannedTripRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostTrip;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Planned trips of events that are not tours (migration 186).
 *
 * - Default trips: outbound (pickup warehouse, else company → event
 *   location, on the pickup or start day) and return (event location →
 *   return warehouse, else pickup warehouse, else company, on the return or
 *   end day). They are created once as soon as the event has a location and
 *   follow later changes of warehouses, location and dates as long as they
 *   are unchanged (default_signature). Deleted default trips do not come
 *   back on their own; restoreDefaults() recreates them and replaces
 *   default trips that were edited in the meantime (their origin is kept in
 *   default_signature as "origin:<kind>").
 * - Editing a trip makes it a custom trip. Distances are calculated with
 *   OpenRouteService when start or destination change; kilometres entered
 *   by hand win.
 * - Tours have no planned trips; their route is the plan (migration 178).
 */
final class PlannedTripService
{
    public const MAX_KM = 20000;
    private const ORIGIN = 'origin:';

    public function __construct(
        private readonly PlannedTripRepositoryInterface $trips,
        private readonly RentalRepositoryInterface $rentals,
        private readonly LogbookLocationResolver $locations,
        private readonly LogbookDistanceService $distances,
        private readonly ?TravelCostRate $rate = null,
    ) {
    }

    /** @return PlannedTrip[] planned trips after bringing the default trips up to date */
    public function forRental(Rental $rental): array
    {
        if ($rental->isTour) {
            return [];
        }
        $trips = $this->trips->findByRental($rental->id);
        $changed = false;
        if (!$rental->plannedTripsInitialized) {
            $created = false;
            foreach ([PlannedTrip::OUTBOUND, PlannedTrip::RETURN] as $kind) {
                $created = $this->createDefault($rental, $kind) || $created;
            }
            if ($created) {
                $rental->plannedTripsInitialized = true;
                $this->rentals->save($rental);
                $changed = true;
            }
        } else {
            foreach ($trips as $trip) {
                if ($trip->defaultKind !== null && $trip->defaultSignature !== $this->signature($rental, $trip->defaultKind)) {
                    $changed = $this->refreshDefault($rental, $trip) || $changed;
                }
            }
        }

        return $changed ? $this->trips->findByRental($rental->id) : $trips;
    }

    /**
     * Saves a new (tripId null) or changed trip; the trip becomes a custom trip.
     *
     * @throws ValidationException
     */
    public function save(string $rentalId, ?string $tripId, ?string $date, ?string $from, ?string $fromText, ?string $to, ?string $toText, ?string $kmOverride): string
    {
        $rental = $this->rental($rentalId);
        $existing = null;
        if ($tripId !== null) {
            $existing = $this->trips->findById($tripId);
            if ($existing === null || $existing->rentalId !== $rental->id) {
                throw new NotFoundException('Die Fahrt wurde nicht gefunden.');
            }
        }

        $errors = [];
        $tripDate = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $date)) ?: null;
        if ($tripDate === null) {
            $errors['tripDate'] = 'Bitte das Datum der Fahrt angeben.';
        }
        $fromLocation = $toLocation = null;
        foreach (['from' => [$from, $fromText], 'to' => [$to, $toText]] as $field => [$choice, $text]) {
            try {
                $location = $this->locations->resolve($choice, $text, $rental->id, $field);
            } catch (ValidationException $e) {
                $errors += $e->errors();
                continue;
            }
            if ($location === null) {
                $errors[$field] = $field === 'from' ? 'Bitte den Startort wählen.' : 'Bitte das Ziel wählen.';
            }
            if ($field === 'from') {
                $fromLocation = $location;
            } else {
                $toLocation = $location;
            }
        }
        $override = null;
        $kmOverride = trim(str_replace(['.', ' '], '', (string) $kmOverride));
        if ($kmOverride !== '') {
            if (preg_match('/^\d+$/', $kmOverride) !== 1 || (int) $kmOverride > self::MAX_KM) {
                $errors['kmOverride'] = 'Bitte die Kilometer als ganze Zahl zwischen 0 und ' . self::MAX_KM . ' angeben.';
            } else {
                $override = (int) $kmOverride;
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        assert($fromLocation !== null && $toLocation !== null);

        $now = new DateTimeImmutable();
        $trip = $existing ?? new PlannedTrip(Uuid::v4(), $rental->id, $this->nextSortOrder($rental->id), null, null, null, null, null, null, null, $now, $now);
        $moved = $trip->from === null || $trip->to === null || !$trip->from->samePlaceAs($fromLocation) || !$trip->to->samePlaceAs($toLocation);
        $trip->tripDate = $tripDate;
        $trip->from = $fromLocation;
        $trip->to = $toLocation;
        $trip->kmOverride = $override;
        if ($trip->defaultKind !== null) {
            $trip->defaultSignature = self::ORIGIN . $trip->defaultKind;
        }
        $trip->defaultKind = null;
        if ($moved || $trip->distanceKm === null) {
            $trip->distanceKm = $this->distance($fromLocation, $toLocation);
        }
        $trip->updatedAt = $now;
        $this->trips->save($trip);

        return $trip->id;
    }

    public function delete(string $rentalId, string $tripId): void
    {
        $trip = $this->trips->findById($tripId);
        if ($trip === null || $trip->rentalId !== $rentalId) {
            throw new NotFoundException('Die Fahrt wurde nicht gefunden.');
        }
        $this->trips->delete($trip->id);
    }

    /** Recreates the default trips (default trips, also edited ones, are replaced; other trips stay). */
    public function restoreDefaults(string $rentalId): int
    {
        $rental = $this->rental($rentalId);
        foreach ($this->trips->findByRental($rental->id) as $trip) {
            if ($trip->defaultKind !== null || str_starts_with((string) $trip->defaultSignature, self::ORIGIN)) {
                $this->trips->delete($trip->id);
            }
        }
        $created = 0;
        foreach ([PlannedTrip::OUTBOUND, PlannedTrip::RETURN] as $kind) {
            $created += $this->createDefault($rental, $kind) ? 1 : 0;
        }
        if ($created === 0) {
            throw new ValidationException(['defaults' => 'Für Standardfahrten braucht die Veranstaltung einen Ort.']);
        }
        if (!$rental->plannedTripsInitialized) {
            $rental->plannedTripsInitialized = true;
            $this->rentals->save($rental);
        }

        return $created;
    }

    /** Calculates the driving distance of every trip again; returns the number of trips without a distance. */
    public function recalculate(string $rentalId): int
    {
        $rental = $this->rental($rentalId);
        $missing = 0;
        foreach ($this->forRental($rental) as $trip) {
            if ($trip->from === null || $trip->to === null) {
                continue;
            }
            $trip->distanceKm = $this->distance($trip->from, $trip->to);
            $trip->updatedAt = new DateTimeImmutable();
            $this->trips->save($trip);
            if ($trip->distanceKm === null && $trip->kmOverride === null) {
                $missing++;
            }
        }

        return $missing;
    }

    /**
     * Travel costs of the planned trips (same shape as for tours): trips with
     * kilometres, price per km of the planned vehicles plus markup.
     */
    public function proposal(Rental $rental): TourTravelCostProposal
    {
        $trips = [];
        $missing = 0;
        foreach ($this->forRental($rental) as $trip) {
            $km = $trip->kilometers();
            if ($km === null) {
                $missing++;
                continue;
            }
            if ($km > 0) {
                $trips[] = new TravelCostTrip($trip->tripDate, $trip->from?->label ?? '', $trip->to?->label ?? '', $km);
            }
        }
        $rate = $this->rate?->forRental($rental->id) ?? ['vehicles' => [], 'costPerKm' => '0.00', 'ratePerKm' => '0.00', 'settings' => null];
        $hints = [];
        if ($rate['vehicles'] === []) {
            $hints[] = 'Keine Fahrzeuge geplant – Fahrtkosten können nicht berechnet werden.';
        }
        if ($missing > 0) {
            $hints[] = $missing === 1 ? 'Bei einer Fahrt fehlen die Kilometer.' : 'Bei ' . $missing . ' Fahrten fehlen die Kilometer.';
        }
        if ($trips === [] && $missing === 0) {
            $hints[] = 'Keine geplanten Fahrten.';
        }

        return new TourTravelCostProposal(
            rentalId: $rental->id,
            enabled: $rental->travelCostsEnabled,
            mode: TravelCostMode::tryFrom($rental->travelCostsMode) ?? TravelCostMode::Summary,
            trips: $trips,
            vehicles: $rate['vehicles'],
            costPerKm: $rate['costPerKm'],
            markupPercent: $rate['settings']?->travelCostMarkupPercent ?? '0.00',
            ratePerKm: $rate['ratePerKm'],
            hints: $hints,
        );
    }

    // --- default trips ------------------------------------------------------------

    /** @return array{date: DateTimeImmutable, from: string, to: string}|null */
    public function defaultPlan(Rental $rental, string $kind): ?array
    {
        $hasLocation = $rental->venueId !== null || trim((string) $rental->eventLocation) !== '';
        if (!$hasLocation) {
            return null;
        }
        $venue = 'event_venue:' . $rental->id;
        $pickup = $rental->pickupWarehouseId !== null ? 'warehouse:' . $rental->pickupWarehouseId : 'company:';
        $returnWarehouse = $rental->returnWarehouseId ?? $rental->pickupWarehouseId;
        $return = $returnWarehouse !== null ? 'warehouse:' . $returnWarehouse : 'company:';

        return $kind === PlannedTrip::OUTBOUND
            ? ['date' => ($rental->pickupAt ?? $rental->startDate)->setTime(0, 0), 'from' => $pickup, 'to' => $venue]
            : ['date' => ($rental->returnAt ?? $rental->endDate)->setTime(0, 0), 'from' => $venue, 'to' => $return];
    }

    private function signature(Rental $rental, string $kind): ?string
    {
        $plan = $this->defaultPlan($rental, $kind);

        return $plan === null ? null : implode('|', [$kind, $plan['date']->format('Y-m-d'), $plan['from'], $plan['to'], (string) $rental->venueId, trim((string) $rental->eventLocation)]);
    }

    private function createDefault(Rental $rental, string $kind): bool
    {
        $plan = $this->defaultPlan($rental, $kind);
        if ($plan === null) {
            return false;
        }
        [$from, $to] = $this->resolvePlan($rental, $plan);
        if ($from === null || $to === null) {
            return false;
        }
        $now = new DateTimeImmutable();
        $this->trips->save(new PlannedTrip(
            Uuid::v4(),
            $rental->id,
            $kind === PlannedTrip::OUTBOUND ? 0 : 1000,
            $plan['date'],
            $from,
            $to,
            $this->distance($from, $to),
            null,
            $kind,
            $this->signature($rental, $kind),
            $now,
            $now,
        ));

        return true;
    }

    private function refreshDefault(Rental $rental, PlannedTrip $trip): bool
    {
        $plan = $this->defaultPlan($rental, (string) $trip->defaultKind);
        if ($plan === null) {
            return false; // the event has no location any more: keep the trip as it is
        }
        [$from, $to] = $this->resolvePlan($rental, $plan);
        if ($from === null || $to === null) {
            return false;
        }
        $trip->tripDate = $plan['date'];
        $trip->from = $from;
        $trip->to = $to;
        $trip->distanceKm = $this->distance($from, $to);
        $trip->defaultSignature = $this->signature($rental, (string) $trip->defaultKind);
        $trip->updatedAt = new DateTimeImmutable();
        $this->trips->save($trip);

        return true;
    }

    /**
     * @param array{date: DateTimeImmutable, from: string, to: string} $plan
     * @return array{0: ?LogbookLocation, 1: ?LogbookLocation}
     */
    private function resolvePlan(Rental $rental, array $plan): array
    {
        try {
            return [
                $this->locations->resolve($plan['from'], null, $rental->id, 'from'),
                $this->locations->resolve($plan['to'], null, $rental->id, 'to'),
            ];
        } catch (ValidationException) {
            return [null, null]; // e.g. the warehouse was deleted
        }
    }

    private function distance(LogbookLocation $from, LogbookLocation $to): ?int
    {
        return $this->distances->suggest($from, $to)['km'];
    }

    private function nextSortOrder(string $rentalId): int
    {
        $max = 0;
        foreach ($this->trips->findByRental($rentalId) as $trip) {
            if ($trip->defaultKind === null) {
                $max = max($max, $trip->sortOrder);
            }
        }

        return $max + 10;
    }

    private function rental(string $rentalId): Rental
    {
        $rental = $this->rentals->findById($rentalId) ?? throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
        if ($rental->isTour) {
            throw new ValidationException(['rental' => 'Touren planen ihre Fahrten über die Route.']);
        }

        return $rental;
    }
}
