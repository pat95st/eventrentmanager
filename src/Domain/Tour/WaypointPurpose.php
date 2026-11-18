<?php

declare(strict_types=1);

namespace App\Domain\Tour;

enum WaypointPurpose: string
{
    case Load = 'load';
    case Overnight = 'overnight';
    case Break = 'break';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Load => 'Laden/Entladen',
            self::Overnight => 'Übernachtung',
            self::Break => 'Pause',
            self::Other => 'Sonstiges',
        };
    }
}
