<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

enum VehicleCategory: string
{
    case Auto = 'auto';
    case Anhaenger = 'anhaenger';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Auto',
            self::Anhaenger => 'Anhänger',
        };
    }
}
