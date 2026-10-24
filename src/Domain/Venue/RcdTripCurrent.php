<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/** RCD-Auslösestrom (Fehlerstromschutzschalter) eines Stromanschlusses. */
enum RcdTripCurrent: string
{
    case Ma10 = '10 mA';
    case Ma30 = '30 mA';
    case Ma100 = '100 mA';
    case Ma300 = '300 mA';
    case Ma500 = '500 mA';
    case None = 'Kein RCD verbaut';

    public function isInstalled(): bool
    {
        return $this !== self::None;
    }

    /** Auslösestrom in Milliampere, oder null, wenn gar kein RCD verbaut ist. */
    public function milliamps(): ?int
    {
        return match ($this) {
            self::Ma10 => 10,
            self::Ma30 => 30,
            self::Ma100 => 100,
            self::Ma300 => 300,
            self::Ma500 => 500,
            self::None => null,
        };
    }

    /** @return RcdTripCurrent[] Alle Werte in fester Anzeigereihenfolge. */
    public static function all(): array
    {
        return [self::Ma10, self::Ma30, self::Ma100, self::Ma300, self::Ma500, self::None];
    }
}
