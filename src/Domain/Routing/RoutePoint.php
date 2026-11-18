<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/** Point of a route: start, stop, waypoint or destination. */
final class RoutePoint
{
    public function __construct(
        public readonly string $label,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $kind, // start | stop | waypoint | end
        public readonly ?string $stopId = null,
    ) {
    }

    /** Distance as the crow flies, in metres. */
    public function distanceTo(self $other): float
    {
        $r = 6371000.0;
        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->latitude);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($other->longitude - $this->longitude);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
