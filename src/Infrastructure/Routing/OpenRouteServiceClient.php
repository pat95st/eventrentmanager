<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Application\Routing\RoutingClientInterface;
use App\Application\Routing\RoutingException;
use App\Domain\Routing\RouteLeg;
use App\Domain\Routing\RoutePoint;
use App\Domain\Routing\RouteProfile;
use App\Domain\Routing\RouteResult;
use App\Domain\Routing\VehicleRestrictions;
use App\Infrastructure\Integration\HttpJsonRequest;

/**
 * Directions of OpenRouteService (https://openrouteservice.org, free API
 * key). Profiles driving-car / driving-hgv; for trucks the dimensions are
 * sent as restrictions.
 */
final class OpenRouteServiceClient implements RoutingClientInterface
{
    private const URL = 'https://api.openrouteservice.org/v2/directions/%s/geojson';

    /** @var callable(string, string, array<string, string>, array<string, mixed>): array{status: int, json: array<string, mixed>|null, raw: string} */
    private $transport;

    /** @param callable|null $transport for tests; default sends the request with curl */
    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? static fn (string $method, string $url, array $headers, array $body) => HttpJsonRequest::send($method, $url, $headers, $body, 30);
    }

    public function route(string $apiKey, array $points, RouteProfile $profile, VehicleRestrictions $restrictions): RouteResult
    {
        $points = array_values($points);
        if (count($points) < 2) {
            throw new RoutingException('zu wenige Punkte');
        }
        $body = [
            'coordinates' => array_map(static fn (RoutePoint $p) => [$p->longitude, $p->latitude], $points),
            'instructions' => false,
        ];
        if ($profile->isTruck()) {
            $body['options'] = ['vehicle_type' => 'hgv'];
            if (!$restrictions->isEmpty()) {
                $body['options']['profile_params'] = ['restrictions' => $restrictions->toArray()];
            }
        }
        try {
            $response = ($this->transport)('POST', sprintf(self::URL, $profile->isTruck() ? 'driving-hgv' : 'driving-car'), [
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/geo+json, application/json',
            ], $body);
        } catch (\Throwable $e) {
            throw new RoutingException('Verbindung zu OpenRouteService fehlgeschlagen', 0, $e);
        }
        if ($response['status'] !== 200 || $response['json'] === null) {
            $message = $response['json']['error']['message'] ?? ($response['json']['error'] ?? null);
            throw new RoutingException('OpenRouteService: ' . (is_string($message) ? $message : 'HTTP ' . $response['status']));
        }

        return self::parse($response['json'], count($points));
    }

    /** @param array<string, mixed> $json GeoJSON answer of the directions endpoint */
    public static function parse(array $json, int $pointCount): RouteResult
    {
        $feature = $json['features'][0] ?? null;
        if (!is_array($feature)) {
            throw new RoutingException('unerwartete Antwort');
        }
        $coordinates = $feature['geometry']['coordinates'] ?? [];
        $segments = $feature['properties']['segments'] ?? [];
        $wayPoints = $feature['properties']['way_points'] ?? [];
        if (!is_array($coordinates) || !is_array($segments) || count($segments) !== $pointCount - 1) {
            throw new RoutingException('unerwartete Antwort');
        }
        $legs = [];
        foreach ($segments as $i => $segment) {
            $from = (int) ($wayPoints[$i] ?? 0);
            $to = (int) ($wayPoints[$i + 1] ?? count($coordinates) - 1);
            $geometry = [];
            foreach (array_slice($coordinates, $from, $to - $from + 1) as $c) {
                $geometry[] = [round((float) $c[1], 6), round((float) $c[0], 6)];
            }
            $legs[] = new RouteLeg($i, $i + 1, (float) ($segment['distance'] ?? 0), (float) ($segment['duration'] ?? 0), $geometry);
        }

        return new RouteResult($legs, false);
    }
}
