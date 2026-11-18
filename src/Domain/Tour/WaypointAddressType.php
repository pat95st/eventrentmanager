<?php

declare(strict_types=1);

namespace App\Domain\Tour;

enum WaypointAddressType: string
{
    case Warehouse = 'warehouse';
    case Company = 'company';
    case Saved = 'saved';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Lager',
            self::Company => 'Firmenadresse',
            self::Saved => 'Gespeicherte Adresse',
            self::Custom => 'Eigene Adresse',
        };
    }
}
