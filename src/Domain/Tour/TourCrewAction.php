<?php

declare(strict_types=1);

namespace App\Domain\Tour;

enum TourCrewAction: string
{
    case Replace = 'replace';
    case Add = 'add';
    case Remove = 'remove';

    public function label(): string
    {
        return match ($this) {
            self::Replace => 'Vertretung',
            self::Add => 'zusätzlich',
            self::Remove => 'nicht dabei',
        };
    }
}
