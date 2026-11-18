<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Domain\Rental\Rental;
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
    ) {
    }

    /** @return string[] */
    public function timingWarningsFor(string $stopId): array
    {
        return array_values(array_map(static fn ($w) => $w['message'], array_filter($this->timingWarnings, static fn ($w) => $w['stopId'] === $stopId)));
    }
}
