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
        private readonly ?TourStopLocator $stopLocator = null,
        private readonly ?\App\Infrastructure\Geocoding\GeocoderInterface $geocoder = null,
    ) {
    }

    public function forRental(string $rentalId): TourRoute
    {
        $rental = $this->rentals->findById($rentalId) ?? throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
        $plan = new TourPlan($this->tours->findStopsByRental($rental->id));
        $settings = $this->settings->get();
        $profile = RouteProfile::tryFrom($rental->routeProfile) ?? RouteProfile::Car;
        $hints = [];

        [$points, $pointHints, $entries] = $this->points($rental, $plan);
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
        $error = null;
        if (count($points) >= 2) {
            if (!$settings->hasApiKey() || $this->client === null) {
                $result = RouteResult::straightLine($points);
                $hints[] = 'Kein API-Schlüssel für die Routenplanung hinterlegt – Verbindungen als Luftlinie, ohne Fahrzeiten.';
            } else {
                $result = $this->calculate($rental->id, (string) $settings->apiKey, $points, $profile, $restrictions, $error);
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
            entries: $entries,
            error: $error,
        );
    }

    /** Geocoding requests per page view (Nominatim allows about one per second). */
    private const MAX_GEOCODING = 5;

    private int $geocodingBudget = self::MAX_GEOCODING;

    /** @return array{0: RoutePoint[], 1: string[], 2: list<array{label: string, kind: string, included: bool, detail: string, editUrl: ?string}>} */
    private function points(Rental $rental, TourPlan $plan): array
    {
        $points = [];
        $hints = [];
        $entries = [];
        $add = function (string $label, string $kind, ?array $coordinates, string $detail, ?string $editUrl, ?string $stopId, bool $notBilled = false, ?int $kmOverride = null) use (&$points, &$entries): void {
            $entries[] = ['label' => $label, 'kind' => $kind, 'included' => $coordinates !== null, 'detail' => $detail, 'editUrl' => $editUrl];
            if ($coordinates !== null) {
                $points[] = new RoutePoint($label, $coordinates[0], $coordinates[1], $kind, $stopId, $notBilled, $kmOverride);
            }
        };

        // Start: stored start, otherwise the pickup warehouse
        $start = $plan->start();
        $startUrl = '/veranstaltungen/' . $rental->id . '/tour/stops/neu?art=start';
        if ($start !== null) {
            $coordinates = $this->locate($start);
            $add($start->title(), 'start', $coordinates, $coordinates !== null ? $start->addressLine() : 'Adresse nicht gefunden', $startUrl, $start->id);
        } else {
            [$label, $coordinates, $detail] = $this->warehouse($rental->pickupWarehouseId, 'Start');
            $add($label, 'start', $coordinates, $detail, $startUrl, null);
        }

        $number = 0;
        foreach ($plan->ordered() as $stop) {
            if ($stop->isStop()) {
                $number++;
            }
            $label = $stop->isStop() ? 'Tourstop ' . $number . ': ' . $stop->title() : $stop->title();
            $coordinates = $this->locate($stop);
            $detail = $coordinates !== null
                ? ($stop->isStop() ? ($stop->venueId !== null ? 'gespeicherter Veranstaltungsort' : 'Ort per Suche gefunden') : $stop->addressLine())
                : ($stop->isStop() ? 'Ort nicht gefunden – bitte einen gespeicherten Veranstaltungsort wählen oder den Ort genauer angeben (z. B. mit Ortsname)' : 'Adresse nicht gefunden – bitte Adresse prüfen');
            $add($label, $stop->isStop() ? 'stop' : 'waypoint', $coordinates, $detail, '/tour-stops/' . $stop->id . '/bearbeiten', $stop->id, $stop->arrivalNotBilled, $stop->arrivalKmOverride);
        }

        // Destination: stored destination, otherwise the return (or pickup) warehouse
        $end = $plan->end();
        $endUrl = '/veranstaltungen/' . $rental->id . '/tour/stops/neu?art=ziel';
        if ($end !== null) {
            $coordinates = $this->locate($end);
            $add($end->title(), 'end', $coordinates, $coordinates !== null ? $end->addressLine() : 'Adresse nicht gefunden', $endUrl, $end->id, $end->arrivalNotBilled, $end->arrivalKmOverride);
        } else {
            [$label, $coordinates, $detail] = $this->warehouse($rental->returnWarehouseId ?? $rental->pickupWarehouseId, 'Ziel');
            $add($label, 'end', $coordinates, $detail, $endUrl, null);
        }

        $missing = count(array_filter($entries, static fn ($e) => !$e['included']));
        if ($missing > 0) {
            $hints[] = $missing === 1
                ? '1 Punkt hat keine Koordinaten und fehlt in der Route (siehe Liste der Routenpunkte).'
                : $missing . ' Punkte haben keine Koordinaten und fehlen in der Route (siehe Liste der Routenpunkte).';
        }

        return [$points, $hints, $entries];
    }

    /** @return array{0: float, 1: float}|null */
    private function locate(TourStop $stop): ?array
    {
        $locator = $this->stopLocator ?? new TourStopLocator($this->venues);
        $before = [$stop->latitude, $stop->longitude];
        $coordinates = $locator->locate($stop, $this->geocodingBudget > 0);
        if ($before !== [$stop->latitude, $stop->longitude]) {
            if ($before === [null, null]) {
                $this->geocodingBudget--;
            }
            $this->tours->saveStop($stop); // remember found coordinates
        } elseif ($coordinates === null) {
            $this->geocodingBudget--;
        }

        return $coordinates;
    }

    /** @return array{0: string, 1: array{0: float, 1: float}|null, 2: string} */
    private function warehouse(?string $warehouseId, string $prefix): array
    {
        $warehouse = $warehouseId !== null ? $this->warehouses->findById($warehouseId) : null;
        if ($warehouse === null) {
            return [$prefix . ': kein Lager hinterlegt', null, 'In der Logistik der Veranstaltung ein Lager wählen oder hier eine Adresse festlegen'];
        }
        if (($warehouse->latitude === null || $warehouse->longitude === null) && $this->geocoder !== null && $this->geocodingBudget-- > 0) {
            try {
                $found = $this->geocoder->geocode($warehouse->street, $warehouse->postalCode, $warehouse->city, $warehouse->country);
            } catch (\Throwable) {
                $found = null;
            }
            if ($found !== null) {
                $warehouse->latitude = (float) $found[0];
                $warehouse->longitude = (float) $found[1];
                $this->warehouses->save($warehouse);
            }
        }
        if ($warehouse->latitude === null || $warehouse->longitude === null) {
            return [$prefix . ': ' . $warehouse->name, null, 'Adresse des Lagers nicht gefunden'];
        }

        return [$prefix . ': ' . $warehouse->name, [(float) $warehouse->latitude, (float) $warehouse->longitude], trim($warehouse->street . ', ' . $warehouse->postalCode . ' ' . $warehouse->city, ', ')];
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
     */
    private function calculate(string $rentalId, string $apiKey, array $points, RouteProfile $profile, VehicleRestrictions $restrictions, ?string &$error): RouteResult
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
            $error = $e->getMessage();

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
