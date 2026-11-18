<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Routing\GoogleMapsLinks;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Routing\RouteCacheRepositoryInterface;
use App\Domain\Routing\RoutePoint;
use App\Domain\Routing\RouteProfile;
use App\Domain\Routing\RouteResult;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Routing\VehicleRestrictions;
use App\Domain\Tour\TourPlan;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Tour\TourStop;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Domain\Warehouse\WarehouseRepositoryInterface;

/**
 * Builds and calculates the route of a tour (migration 178): start (stored
 * start, else the pickup warehouse) → stops and waypoints → destination
 * (stored destination, else the return or pickup warehouse). Without an API
 * key, or when the service fails, the points are connected as the crow flies.
 */
final class TourRouteService
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly TourRepositoryInterface $tours,
        private readonly VenueRepositoryInterface $venues,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly PlannedVehicleRepositoryInterface $plannedVehicles,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly RoutingSettingsRepositoryInterface $settings,
        private readonly RouteCacheRepositoryInterface $cache,
        private readonly ?RoutingClientInterface $client = null,
    ) {
    }

    public function forRental(string $rentalId): TourRoute
    {
        $rental = $this->rentals->findById($rentalId) ?? throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
        $plan = new TourPlan($this->tours->findStopsByRental($rental->id));
        $settings = $this->settings->get();
        $profile = RouteProfile::tryFrom($rental->routeProfile) ?? RouteProfile::Car;
        $hints = [];

        [$points, $pointHints] = $this->points($rental, $plan);
        $hints = array_merge($hints, $pointHints);

        $restrictions = new VehicleRestrictions();
        if ($profile === RouteProfile::HgvVehicles) {
            $restrictions = $this->restrictions($rental->id);
            if ($restrictions->isEmpty() && $restrictions->missing === []) {
                $hints[] = 'Es sind keine Fahrzeuge geplant – die LKW-Route berücksichtigt keine Maße.';
            }
            foreach ($restrictions->missing as $missing) {
                $hints[] = $missing . ' – die Route berücksichtigt diese Einschränkung nicht.';
            }
        }

        $result = null;
        if (count($points) >= 2) {
            if (!$settings->hasApiKey() || $this->client === null) {
                $result = RouteResult::straightLine($points);
                $hints[] = 'Kein API-Schlüssel für die Routenplanung hinterlegt – Verbindungen als Luftlinie, ohne Fahrzeiten.';
            } else {
                $result = $this->calculate($rental->id, (string) $settings->apiKey, $points, $profile, $restrictions, $hints);
            }
        } else {
            $hints[] = 'Für eine Route werden mindestens zwei Punkte mit Koordinaten gebraucht.';
        }

        return new TourRoute(
            rental: $rental,
            points: $points,
            result: $result,
            profile: $profile,
            restrictions: $restrictions,
            hints: $hints,
            timingWarnings: $result !== null ? $this->timingWarnings($plan, $points, $result, $settings->bufferMinutes) : [],
            googleLinks: GoogleMapsLinks::build($points),
            hasApiKey: $settings->hasApiKey(),
            bufferMinutes: $settings->bufferMinutes,
        );
    }

    /** @return array{0: RoutePoint[], 1: string[]} */
    private function points(Rental $rental, TourPlan $plan): array
    {
        $points = [];
        $hints = [];

        $start = $plan->start();
        if ($start !== null) {
            $start->hasCoordinates()
                ? $points[] = new RoutePoint($start->title(), (float) $start->latitude, (float) $start->longitude, 'start', $start->id)
                : $hints[] = 'Für den Start „' . $start->title() . '“ sind keine Koordinaten bekannt.';
        } else {
            $warehousePoint = $this->warehousePoint($rental->pickupWarehouseId, 'start', 'Start');
            if ($warehousePoint !== null) {
                $points[] = $warehousePoint;
            } else {
                $hints[] = 'Kein Start festgelegt und kein Ausgabelager mit Koordinaten – die Route beginnt beim ersten Stop.';
            }
        }

        $number = 0;
        foreach ($plan->ordered() as $stop) {
            if ($stop->isStop()) {
                $number++;
            }
            $label = $stop->isStop() ? 'Stop ' . $number . ': ' . $stop->title() : $stop->title();
            $coordinates = $this->coordinatesOf($stop);
            if ($coordinates === null) {
                $hints[] = $label . ': keine Koordinaten' . ($stop->isStop() ? ' (Ort ist kein gespeicherter Veranstaltungsort oder dessen Adresse wurde nicht gefunden)' : '') . ' – wird übersprungen.';
                continue;
            }
            $points[] = new RoutePoint($label, $coordinates[0], $coordinates[1], $stop->isStop() ? 'stop' : 'waypoint', $stop->id);
        }

        $end = $plan->end();
        if ($end !== null) {
            $end->hasCoordinates()
                ? $points[] = new RoutePoint($end->title(), (float) $end->latitude, (float) $end->longitude, 'end', $end->id)
                : $hints[] = 'Für das Ziel „' . $end->title() . '“ sind keine Koordinaten bekannt.';
        } else {
            $warehousePoint = $this->warehousePoint($rental->returnWarehouseId ?? $rental->pickupWarehouseId, 'end', 'Ziel');
            if ($warehousePoint !== null) {
                $points[] = $warehousePoint;
            }
        }

        return [$points, $hints];
    }

    /** @return array{0: float, 1: float}|null */
    private function coordinatesOf(TourStop $stop): ?array
    {
        if ($stop->isStop()) {
            $venue = $stop->venueId !== null ? $this->venues->findById($stop->venueId) : null;

            return $venue !== null && $venue->latitude !== null && $venue->longitude !== null ? [(float) $venue->latitude, (float) $venue->longitude] : null;
        }

        return $stop->hasCoordinates() ? [(float) $stop->latitude, (float) $stop->longitude] : null;
    }

    private function warehousePoint(?string $warehouseId, string $kind, string $prefix): ?RoutePoint
    {
        $warehouse = $warehouseId !== null ? $this->warehouses->findById($warehouseId) : null;
        if ($warehouse === null || $warehouse->latitude === null || $warehouse->longitude === null) {
            return null;
        }

        return new RoutePoint($prefix . ': ' . $warehouse->name, (float) $warehouse->latitude, (float) $warehouse->longitude, $kind);
    }

    private function restrictions(string $rentalId): VehicleRestrictions
    {
        $planned = [];
        foreach ($this->plannedVehicles->findByRental($rentalId) as $entry) {
            $vehicle = $this->vehicles->findById($entry->vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $planned[] = ['vehicle' => $vehicle, 'towing' => $entry->towingVehicleId !== null ? $this->vehicles->findById($entry->towingVehicleId) : null];
        }

        return VehicleRestrictions::fromPlannedVehicles($planned);
    }

    /**
     * @param RoutePoint[] $points
     * @param string[] $hints
     */
    private function calculate(string $rentalId, string $apiKey, array $points, RouteProfile $profile, VehicleRestrictions $restrictions, array &$hints): RouteResult
    {
        $hash = hash('sha256', json_encode([
            array_map(static fn (RoutePoint $p) => [round($p->latitude, 6), round($p->longitude, 6)], $points),
            $profile->value,
            $restrictions->toArray(),
        ], JSON_THROW_ON_ERROR));
        $cached = $this->cache->find($rentalId, $hash);
        if ($cached !== null) {
            return RouteResult::fromArray($cached);
        }
        try {
            $result = $this->client?->route($apiKey, $points, $profile, $restrictions) ?? RouteResult::straightLine($points);
        } catch (RoutingException $e) {
            $hints[] = 'Die Route konnte nicht berechnet werden (' . $e->getMessage() . ') – Verbindungen als Luftlinie.';

            return RouteResult::straightLine($points);
        }
        $this->cache->store($rentalId, $hash, $result->toArray());

        return $result;
    }

    /**
     * Warns when teardown end + driving time (+ waypoint stays) + buffer
     * lies after the setup of the next stop.
     *
     * @param RoutePoint[] $points
     * @return list<array{stopId: string, message: string}>
     */
    public function timingWarnings(TourPlan $plan, array $points, RouteResult $result, int $bufferMinutes): array
    {
        if ($result->straightLine) {
            return [];
        }
        $indexByStop = [];
        foreach ($points as $i => $point) {
            if ($point->stopId !== null) {
                $indexByStop[$point->stopId] = $i;
            }
        }
        $durations = [];
        foreach ($result->legs as $leg) {
            $durations[$leg->to] = (float) $leg->durationS;
        }

        $warnings = [];
        $stops = $plan->stops();
        $ordered = $plan->ordered();
        for ($k = 1, $n = count($stops); $k < $n; $k++) {
            $previous = $stops[$k - 1];
            $next = $stops[$k];
            if (!isset($indexByStop[$previous->id], $indexByStop[$next->id])) {
                continue;
            }
            $leave = $previous->occupiedUntil();
            $arriveBy = $next->occupiedFrom();
            if ($leave === null || $arriveBy === null) {
                continue;
            }
            $drive = 0.0;
            for ($i = $indexByStop[$previous->id] + 1; $i <= $indexByStop[$next->id]; $i++) {
                $drive += $durations[$i] ?? 0.0;
            }
            // Planned stays at waypoints in between (arrival → departure).
            $stay = 0;
            $between = false;
            foreach ($ordered as $item) {
                if ($item->id === $previous->id) {
                    $between = true;
                    continue;
                }
                if ($item->id === $next->id) {
                    break;
                }
                if ($between && $item->arriveAt !== null && $item->departAt !== null) {
                    $stay += max(0, $item->departAt->getTimestamp() - $item->arriveAt->getTimestamp());
                }
            }
            $available = ($arriveBy->getTimestamp() - $leave->getTimestamp()) / 60;
            $needed = ($drive + $stay) / 60 + $bufferMinutes;
            if ($needed > $available) {
                $warnings[] = [
                    'stopId' => $next->id,
                    'message' => sprintf(
                        'Zwischen dem Abbau-Ende in „%s“ (%s) und dem Aufbau in „%s“ (%s) bleiben %s – nötig sind %s Fahrzeit%s plus %d min Puffer.',
                        $previous->title(),
                        $leave->format('d.m. H:i'),
                        $next->title(),
                        $arriveBy->format('d.m. H:i'),
                        self::minutes($available),
                        self::minutes($drive / 60),
                        $stay > 0 ? ' und ' . self::minutes($stay / 60) . ' Aufenthalt' : '',
                        $bufferMinutes,
                    ),
                ];
            }
        }

        return $warnings;
    }

    private static function minutes(float $minutes): string
    {
        $minutes = (int) round($minutes);
        if ($minutes < 0) {
            return 'keine Zeit';
        }

        return $minutes >= 60 ? intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . ' min' : $minutes . ' min';
    }
}
