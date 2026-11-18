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
 * Directions of OpenRouteService (HeiGIT, free API key from
 * account.heigit.org). Profiles driving-car / driving-hgv; for trucks the
 * dimensions are sent as restrictions.
 *
 * Since November 2026 the API is only served by api.heigit.org (the former
 * host api.openrouteservice.org was shut down); the key is sent unchanged
 * in the Authorization header.
 */
final class OpenRouteServiceClient implements RoutingClientInterface
{
    public const URL = 'https://api.heigit.org/openrouteservice/v2/directions/%s/geojson';

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
            throw new RoutingException('Verbindung zu OpenRouteService fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
        if ($response['status'] !== 200 || $response['json'] === null) {
            throw new RoutingException(self::errorMessage($response['status'], $response['json'], $response['raw']));
        }

        return self::parse($response['json'], count($points));
    }

    /** @param array<string, mixed>|null $json */
    public static function errorMessage(int $status, ?array $json, string $raw): string
    {
        $message = $json['error']['message'] ?? ($json['error'] ?? ($json['message'] ?? null));
        $detail = is_string($message) && $message !== '' ? $message : mb_substr(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('/</', ' <', $raw)))), 0, 160);
        $hint = match (true) {
            $status === 401, $status === 403 => 'API-Schlüssel ungültig oder ohne Berechtigung – bitte den Schlüssel aus account.heigit.org neu kopieren',
            $status === 404 => 'Endpunkt nicht gefunden',
            $status === 429 => 'Tageskontingent bzw. Anfragelimit erreicht',
            $status === 0 => 'keine Antwort vom Server',
            default => null,
        };

        return 'OpenRouteService HTTP ' . $status . ($hint !== null ? ' – ' . $hint : '') . ($detail !== '' ? ' (' . $detail . ')' : '');
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
