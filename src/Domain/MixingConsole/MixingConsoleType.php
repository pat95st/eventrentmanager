<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/** Bauart eines Tonmischpults (Migration 154). */
enum MixingConsoleType: string
{
    case Analog = 'analog';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::Analog => 'Analog',
            self::Digital => 'Digital',
        };
    }
}
