<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Domain\Rental\Rental;
use App\Domain\Routing\RouteLeg;
use App\Domain\Routing\RoutePoint;
use App\Domain\Routing\RouteProfile;
use App\Domain\Routing\RouteResult;
use App\Domain\Routing\VehicleRestrictions;

/** Route of a tour with hints, timing warnings and Google Maps links. */
final class TourRoute
{
    /**
     * @param RoutePoint[] $points
     * @param string[] $hints problems that make the route less accurate
     * @param list<array{stopId: string, message: string}> $timingWarnings
     * @param list<array{url: string, from: string, to: string}> $googleLinks
     * @param list<array{label: string, kind: string, included: bool, detail: string, editUrl: ?string}> $entries all planned points in driving order
     */
    public function __construct(
        public readonly Rental $rental,
        public readonly array $points,
        public readonly ?RouteResult $result,
        public readonly RouteProfile $profile,
        public readonly VehicleRestrictions $restrictions,
        public readonly array $hints,
        public readonly array $timingWarnings,
        public readonly array $googleLinks,
        public readonly bool $hasApiKey,
        public readonly int $bufferMinutes,
        public readonly array $entries = [],
        public readonly ?string $error = null, // why the route service failed (straight lines shown instead)
    ) {
    }

    /** Whether a leg counts for the billable kilometres (migration 179). */
    public function isLegBilled(RouteLeg $leg): bool
    {
        return !($this->points[$leg->to]->arrivalNotBilled ?? false);
    }

    /** Kilometres to bill in metres: total distance without the legs marked as not billed. */
    public function billableDistanceM(): ?float
    {
        if ($this->result === null) {
            return null;
        }

        return array_sum(array_map(fn (RouteLeg $l) => $this->isLegBilled($l) ? $l->distanceM : 0.0, $this->result->legs));
    }

    /** @return string[] */
    public function timingWarningsFor(string $stopId): array
    {
        return array_values(array_map(static fn ($w) => $w['message'], array_filter($this->timingWarnings, static fn ($w) => $w['stopId'] === $stopId)));
    }
}
