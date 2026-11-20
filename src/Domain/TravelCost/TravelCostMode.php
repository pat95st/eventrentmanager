<?php

declare(strict_types=1);

namespace App\Domain\TravelCost;

/** How the travel costs of a tour appear on a document (migration 182). */
enum TravelCostMode: string
{
    case Summary = 'summary';   // one line with all kilometres
    case Itemized = 'itemized'; // one line per trip

    public function label(): string
    {
        return match ($this) {
            self::Summary => 'Sammelposten',
            self::Itemized => 'Einzelne Fahrten',
        };
    }
}
