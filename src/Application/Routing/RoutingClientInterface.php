<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Domain\Routing\RoutePoint;
use App\Domain\Routing\RouteProfile;
use App\Domain\Routing\RouteResult;
use App\Domain\Routing\VehicleRestrictions;

interface RoutingClientInterface
{
    /**
     * @param RoutePoint[] $points at least two
     * @throws RoutingException when the service cannot calculate the route
     */
    public function route(string $apiKey, array $points, RouteProfile $profile, VehicleRestrictions $restrictions): RouteResult;
}
