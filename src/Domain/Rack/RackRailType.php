<?php

declare(strict_types=1);

namespace App\Domain\Rack;

enum RackRailType: string
{
    case Front = 'front';
    case FrontBack = 'front_back';

    public function label(): string
    {
        return match ($this) {
            self::Front => 'Rackschiene nur vorne',
            self::FrontBack => 'Rackschienen vorne und hinten',
        };
    }
}
