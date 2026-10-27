<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Rundungsrichtung für PriceRoundingStep (siehe dortige Klassen-Doc) — frei
 * wählbar statt fest vorgegeben (siehe Entscheidung in der
 * Aufgabenstellung), da je nach Geschäftsmodell sowohl "kaufmännisch runden"
 * als auch "immer zugunsten des Vermieters aufrunden" gewünscht sein kann.
 */
enum PriceRoundingDirection: string
{
    case Nearest = 'nearest';
    case Up = 'up';
    case Down = 'down';

    public function label(): string
    {
        return match ($this) {
            self::Nearest => 'Kaufmännisch runden (auf den nächsten Schritt)',
            self::Up => 'Immer aufrunden',
            self::Down => 'Immer abrunden',
        };
    }
}
