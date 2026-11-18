<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Links that open a route in Google Maps (e.g. on the driver's phone).
 * Google allows only a few waypoints per link, so long routes are split
 * into several consecutive links (each one starts where the previous ended).
 */
final class GoogleMapsLinks
{
    public const MAX_WAYPOINTS = 9;

    /**
     * @param RoutePoint[] $points
     * @return list<array{url: string, from: string, to: string}>
     */
    public static function build(array $points): array
    {
        $points = array_values($points);
        if (count($points) < 2) {
            return [];
        }
        $links = [];
        $chunk = self::MAX_WAYPOINTS + 1; // points per link apart from the origin
        for ($start = 0; $start < count($points) - 1; $start += $chunk) {
            $slice = array_slice($points, $start, $chunk + 1);
            $origin = $slice[0];
            $destination = $slice[count($slice) - 1];
            $waypoints = array_slice($slice, 1, -1);
            $query = [
                'api' => '1',
                'origin' => self::coordinates($origin),
                'destination' => self::coordinates($destination),
                'travelmode' => 'driving',
            ];
            if ($waypoints !== []) {
                $query['waypoints'] = implode('|', array_map(self::coordinates(...), $waypoints));
            }
            $links[] = [
                'url' => 'https://www.google.com/maps/dir/?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
                'from' => $origin->label,
                'to' => $destination->label,
            ];
        }
        return $links;
    }

    private static function coordinates(RoutePoint $point): string
    {
        return sprintf('%.6F,%.6F', $point->latitude, $point->longitude);
    }
}
