<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/** Leitungsschutzschalter-Größe eines Stromanschlusses. */
enum CircuitBreakerRating: string
{
    case A6 = '6 A';
    case A10 = '10 A';
    case A13 = '13 A';
    case A16 = '16 A';
    case A20 = '20 A';
    case A25 = '25 A';
    case A32 = '32 A';
    case A40 = '40 A';
    case A50 = '50 A';
    case A63 = '63 A';
    case A80 = '80 A';
    case A100 = '100 A';
    case A125 = '125 A';

    /** Nennwert als Zahl (z. B. "32 A" → 32), für Vergleiche/Warnungen. */
    public function amps(): int
    {
        return (int) $this->value;
    }

    /** @return CircuitBreakerRating[] Alle Werte in fester (aufsteigender) Anzeigereihenfolge. */
    public static function all(): array
    {
        return [
            self::A6, self::A10, self::A13, self::A16, self::A20, self::A25, self::A32,
            self::A40, self::A50, self::A63, self::A80, self::A100, self::A125,
        ];
    }
}
