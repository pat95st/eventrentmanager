<?php

declare(strict_types=1);

namespace App\Application\TravelCost;

use App\Application\Routing\TourRoute;
use App\Application\Routing\TourRouteService;
use App\Domain\Rental\Rental;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Routing\RoutePoint;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Tour\TourStop;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostTrip;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use DateTimeImmutable;

/**
 * Travel costs of a tour (migration 182): the billed legs of the route are
 * grouped into trips from stop to stop (waypoints belong to the trip, start
 * and destination count as well), each trip is rounded to whole kilometres.
 * The price per km is the sum of the cost per km of all planned vehicles
 * plus the markup from the vehicle settings, rounded to whole cents.
 */
final class TourTravelCostCalculator
{
    public function __construct(
        private readonly TourRepositoryInterface $tours,
        private readonly PlannedVehicleRepositoryInterface $plannedVehicles,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly VehicleSettingsRepositoryInterface $vehicleSettings,
        private readonly ?TourRouteService $routes = null,
    ) {
    }

    public function forRental(string $rentalId): TourTravelCostProposal
    {
        if ($this->routes === null) {
            throw new \LogicException('No route service configured.');
        }

        return $this->fromRoute($this->routes->forRental($rentalId));
    }

    public function fromRoute(TourRoute $route): TourTravelCostProposal
    {
        $rental = $route->rental;
        $hints = [];

        $stops = [];
        foreach ($this->tours->findStopsByRental($rental->id) as $stop) {
            $stops[$stop->id] = $stop;
        }
        $trips = $this->trips($route, $rental, $stops);

        $settings = $this->vehicleSettings->get();
        $vehicles = [];
        $cost = 0.0;
        $seen = [];
        foreach ($this->plannedVehicles->findByRental($rental->id) as $planned) {
            if (isset($seen[$planned->vehicleId])) {
                continue;
            }
            $seen[$planned->vehicleId] = true;
            $vehicle = $this->vehicles->findById($planned->vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $vehicleCost = $vehicle->effectiveCostPerKm($settings);
            $cost += (float) $vehicleCost;
            $vehicles[] = ['name' => $vehicle->name, 'costPerKm' => $vehicleCost, 'isDefault' => $vehicle->usesDefaultCostPerKm()];
        }

        if ($vehicles === []) {
            $hints[] = 'Keine Fahrzeuge geplant – Fahrtkosten können nicht berechnet werden.';
        }
        if ($route->result === null) {
            $hints[] = 'Es gibt noch keine Route – Fahrtkosten können nicht berechnet werden.';
        } elseif ($trips === []) {
            $hints[] = 'Keine abzurechnenden Fahrten.';
        }
        $straightLine = $route->result !== null && $route->result->straightLine;
        if ($straightLine && $trips !== []) {
            $hints[] = 'Die Kilometer sind Luftlinie und damit zu niedrig – bitte bei den Fahrten die tatsächlichen Kilometer eintragen oder einen API-Schlüssel für die Routenplanung hinterlegen.';
        }

        return new TourTravelCostProposal(
            rentalId: $rental->id,
            enabled: $rental->travelCostsEnabled,
            mode: TravelCostMode::tryFrom($rental->travelCostsMode) ?? TravelCostMode::Summary,
            trips: $trips,
            vehicles: $vehicles,
            costPerKm: number_format($cost, 2, '.', ''),
            markupPercent: $settings->travelCostMarkupPercent,
            ratePerKm: $settings->billedRatePerKm($cost),
            hints: $hints,
            straightLine: $straightLine,
        );
    }

    /**
     * @param array<string, TourStop> $stops
     * @return TravelCostTrip[]
     */
    private function trips(TourRoute $route, Rental $rental, array $stops): array
    {
        if ($route->result === null) {
            return [];
        }
        $points = $route->points;
        $trips = [];
        $origin = 0;
        $meters = 0.0;
        $billed = false;
        foreach ($route->result->legs as $leg) {
            if ($route->isLegBilled($leg)) {
                $meters += $route->legBillingDistanceM($leg);
                $billed = true;
            }
            $to = $points[$leg->to] ?? null;
            if ($to === null || $to->kind === 'waypoint') {
                continue;
            }
            $km = (int) round($meters / 1000);
            if ($billed && $km > 0 && isset($points[$origin])) {
                $date = $this->departure($points[$origin], $rental, $stops) ?? $this->arrival($to, $rental, $stops);
                $trips[] = new TravelCostTrip(
                    $date?->setTime(0, 0),
                    self::label($points[$origin]),
                    self::label($to),
                    $km,
                );
            }
            $origin = $leg->to;
            $meters = 0.0;
            $billed = false;
        }

        return $trips;
    }

    /** @param array<string, TourStop> $stops */
    private function departure(RoutePoint $point, Rental $rental, array $stops): ?DateTimeImmutable
    {
        $stop = $point->stopId !== null ? ($stops[$point->stopId] ?? null) : null;
        if ($stop !== null) {
            return $stop->occupiedUntil();
        }

        return $point->kind === 'start' ? ($rental->pickupAt ?? $rental->startDate) : null;
    }

    /** @param array<string, TourStop> $stops */
    private function arrival(RoutePoint $point, Rental $rental, array $stops): ?DateTimeImmutable
    {
        $stop = $point->stopId !== null ? ($stops[$point->stopId] ?? null) : null;
        if ($stop !== null) {
            return $stop->occupiedFrom();
        }

        return $point->kind === 'end' ? ($rental->returnAt ?? $rental->endDate) : null;
    }

    /** Name of a route point for documents: without "Tourstop n:", "Start:" or "Ziel:". */
    public static function label(RoutePoint $point): string
    {
        $label = preg_replace('/^(Tourstop \d+|Start|Ziel): /u', '', $point->label) ?? $point->label;
        // Default start/destination is a warehouse: "Start: Hauptlager" → "Lager Hauptlager" unless the name says so.
        if ($point->stopId === null && in_array($point->kind, ['start', 'end'], true) && mb_stripos($label, 'lager') === false) {
            $label = 'Lager ' . $label;
        }

        return $label;
    }
}
