<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/** Vehicle profile used to calculate the route of a tour (migration 178). */
enum RouteProfile: string
{
    case Car = 'car';
    case Hgv = 'hgv';
    case HgvVehicles = 'hgv_vehicles';

    public function label(): string
    {
        return match ($this) {
            self::Car => 'PKW',
            self::Hgv => 'LKW (ohne Maße)',
            self::HgvVehicles => 'LKW mit Maßen der geplanten Fahrzeuge',
        };
    }

    public function isTruck(): bool
    {
        return $this !== self::Car;
    }
}
