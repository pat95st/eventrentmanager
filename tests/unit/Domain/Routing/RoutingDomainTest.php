<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Routing;

use App\Domain\Routing\GoogleMapsLinks;
use App\Domain\Routing\RoutePoint;
use App\Domain\Routing\RouteResult;
use App\Domain\Routing\VehicleRestrictions;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Infrastructure\Routing\OpenRouteServiceClient;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class RoutingDomainTest extends Unit
{
    public static function vehicle(string $id, VehicleCategory $category, ?string $height, ?string $width, ?string $length, ?string $weight): Vehicle
    {
        $now = new DateTimeImmutable();

        return new Vehicle($id, $category, 'Fahrzeug ' . $id, null, null, null, null, null, false, null, null, null, null, $now, $now,
            heightCm: $height, widthCm: $width, lengthCm: $length, grossWeightKg: $weight);
    }

    public function testStrictestValuesAndTrailerCombination(): void
    {
        $truck = self::vehicle('lkw', VehicleCategory::Auto, '340', '255', '800', '7490');
        $van = self::vehicle('van', VehicleCategory::Auto, '280', '220', '600', '3500');
        $trailer = self::vehicle('trailer', VehicleCategory::Anhaenger, '250', '230', '450', '2000');

        $restrictions = VehicleRestrictions::fromPlannedVehicles([
            ['vehicle' => $truck, 'towing' => null],
            ['vehicle' => $van, 'towing' => null],
            ['vehicle' => $trailer, 'towing' => $van],
        ]);

        $this->assertSame(3.4, $restrictions->heightM);
        $this->assertSame(2.55, $restrictions->widthM);
        $this->assertSame(10.5, $restrictions->lengthM);   // van 6 m + trailer 4,5 m
        $this->assertSame(7.49, $restrictions->weightT);   // truck is heavier than van + trailer (5,5 t)
        $this->assertSame([], $restrictions->missing);
        $this->assertSame(['height' => 3.4, 'width' => 2.55, 'length' => 10.5, 'weight' => 7.49], $restrictions->toArray());
    }

    public function testMissingDimensionsAreReportedAndLeftOut(): void
    {
        $restrictions = VehicleRestrictions::fromPlannedVehicles([
            ['vehicle' => self::vehicle('a', VehicleCategory::Auto, null, '250', '700', null), 'towing' => null],
        ]);

        $this->assertNull($restrictions->heightM);
        $this->assertSame(2.5, $restrictions->widthM);
        $this->assertNull($restrictions->weightT);
        $this->assertSame(['Höhe von „Fahrzeug a“ fehlt', 'Zulässiges Gesamtgewicht von „Fahrzeug a“ fehlt'], $restrictions->missing);
    }

    public function testGoogleLinksAreSplitAfterNineWaypoints(): void
    {
        $points = [];
        for ($i = 0; $i < 13; $i++) {
            $points[] = new RoutePoint('P' . $i, 50.0 + $i / 10, 8.0, 'stop');
        }
        $links = GoogleMapsLinks::build($points);

        $this->assertCount(2, $links);
        $this->assertSame(['P0', 'P10'], [$links[0]['from'], $links[0]['to']]);
        $this->assertSame(['P10', 'P12'], [$links[1]['from'], $links[1]['to']]);
        parse_str((string) parse_url($links[0]['url'], PHP_URL_QUERY), $query);
        $this->assertCount(9, explode('|', (string) $query['waypoints']));
        $this->assertSame('50.000000,8.000000', $query['origin']);
        $this->assertSame([], GoogleMapsLinks::build([$points[0]]));
    }

    public function testStraightLineDistance(): void
    {
        $hamburg = new RoutePoint('Hamburg', 53.5511, 9.9937, 'start');
        $kassel = new RoutePoint('Kassel', 51.3127, 9.4797, 'stop');
        $result = RouteResult::straightLine([$hamburg, $kassel]);

        $this->assertTrue($result->straightLine);
        $this->assertEqualsWithDelta(252000, $result->totalDistanceM(), 3000);
        $this->assertNull($result->totalDurationS());
        $this->assertEquals($result, RouteResult::fromArray($result->toArray()));
    }

    public function testOpenRouteServiceAnswerIsSplitIntoLegs(): void
    {
        $json = ['features' => [[
            'geometry' => ['coordinates' => [[10.0, 53.5], [9.8, 52.0], [9.5, 51.3], [11.0, 49.0], [11.5, 48.1]]],
            'properties' => [
                'segments' => [['distance' => 300000.0, 'duration' => 10800.0], ['distance' => 480000.0, 'duration' => 16200.0]],
                'way_points' => [0, 2, 4],
            ],
        ]]];
        $result = OpenRouteServiceClient::parse($json, 3);

        $this->assertFalse($result->straightLine);
        $this->assertCount(2, $result->legs);
        $this->assertSame([[53.5, 10.0], [52.0, 9.8], [51.3, 9.5]], $result->legs[0]->geometry);
        $this->assertSame(27000.0, $result->totalDurationS());
    }

    public function testOpenRouteServiceErrorsAreExplained(): void
    {
        $this->assertSame(
            'OpenRouteService HTTP 403 – API-Schlüssel ungültig oder ohne Berechtigung – bitte den Schlüssel aus account.heigit.org neu kopieren (Access to this API has been disallowed)',
            OpenRouteServiceClient::errorMessage(403, ['error' => 'Access to this API has been disallowed'], ''),
        );
        $this->assertSame(
            'OpenRouteService HTTP 400 (Could not find routable point within a radius of 350.0 meters)',
            OpenRouteServiceClient::errorMessage(400, ['error' => ['code' => 2010, 'message' => 'Could not find routable point within a radius of 350.0 meters']], ''),
        );
        $this->assertSame('OpenRouteService HTTP 400 (400 Bad Request nginx)', OpenRouteServiceClient::errorMessage(400, null, '<html><h1>400 Bad Request</h1>nginx</html>'));

        $client = new OpenRouteServiceClient(static fn () => ['status' => 429, 'json' => ['error' => 'Rate limit exceeded'], 'raw' => '']);
        try {
            $client->route('k', [new RoutePoint('A', 53.5, 10.0, 'start'), new RoutePoint('B', 51.3, 9.5, 'end')], \App\Domain\Routing\RouteProfile::Car, new VehicleRestrictions());
            $this->fail('RoutingException expected');
        } catch (\App\Application\Routing\RoutingException $e) {
            $this->assertStringContainsString('HTTP 429 – Tageskontingent', $e->getMessage());
        }
    }

    public function testOpenRouteServiceRequestForTrucks(): void
    {
        $sent = null;
        $client = new OpenRouteServiceClient(static function (string $method, string $url, array $headers, array $body) use (&$sent): array {
            $sent = compact('url', 'headers', 'body');

            return ['status' => 200, 'raw' => '', 'json' => ['features' => [[
                'geometry' => ['coordinates' => [[10.0, 53.5], [9.5, 51.3]]],
                'properties' => ['segments' => [['distance' => 1.0, 'duration' => 2.0]], 'way_points' => [0, 1]],
            ]]]];
        });
        $client->route('key-123', [new RoutePoint('A', 53.5, 10.0, 'start'), new RoutePoint('B', 51.3, 9.5, 'end')], \App\Domain\Routing\RouteProfile::HgvVehicles, new VehicleRestrictions(3.4, null, 8.0, 7.49));

        $this->assertSame('https://api.heigit.org/openrouteservice/v2/directions/driving-hgv/geojson', $sent['url']);
        $this->assertSame('key-123', $sent['headers']['Authorization']);
        $this->assertSame([[10.0, 53.5], [9.5, 51.3]], $sent['body']['coordinates']);
        $this->assertSame(['height' => 3.4, 'length' => 8.0, 'weight' => 7.49], $sent['body']['options']['profile_params']['restrictions']);
    }
}
