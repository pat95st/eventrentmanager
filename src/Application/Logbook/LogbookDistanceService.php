<?php

declare(strict_types=1);

namespace App\Application\Logbook;

use App\Application\Routing\RoutingClientInterface;
use App\Domain\Logbook\LogbookLocation;
use App\Domain\Routing\RoutePoint;
use App\Domain\Routing\RouteProfile;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Routing\VehicleRestrictions;

/**
 * Suggests the driving distance of a trip (migration 184) with
 * OpenRouteService (car profile). Without an API key or coordinates there is
 * no suggestion; a straight line is never offered as driven kilometres.
 */
final class LogbookDistanceService
{
    public function __construct(
        private readonly RoutingSettingsRepositoryInterface $settings,
        private readonly ?RoutingClientInterface $client = null,
    ) {
    }

    /** @return array{km: ?int, hint: ?string} one-way distance in whole km */
    public function suggest(LogbookLocation $from, LogbookLocation $to): array
    {
        $settings = $this->settings->get();
        if ($this->client === null || !$settings->hasApiKey()) {
            return ['km' => null, 'hint' => 'Keine Routenplanung eingerichtet – bitte die Kilometer eintragen.'];
        }
        if (!$from->hasCoordinates() || !$to->hasCoordinates()) {
            return ['km' => null, 'hint' => 'Start oder Ziel wurde nicht auf der Karte gefunden – bitte die Kilometer eintragen.'];
        }
        $points = [
            new RoutePoint($from->label, (float) $from->latitude, (float) $from->longitude, 'start'),
            new RoutePoint($to->label, (float) $to->latitude, (float) $to->longitude, 'end'),
        ];
        try {
            $result = $this->client->route((string) $settings->apiKey, $points, RouteProfile::Car, new VehicleRestrictions());
        } catch (\Throwable) {
            return ['km' => null, 'hint' => 'Die Strecke konnte nicht berechnet werden – bitte die Kilometer eintragen.'];
        }
        if ($result->straightLine) {
            return ['km' => null, 'hint' => 'Die Strecke konnte nicht berechnet werden – bitte die Kilometer eintragen.'];
        }

        return ['km' => (int) round($result->totalDistanceM() / 1000), 'hint' => null];
    }
}
