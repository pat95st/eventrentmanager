<?php

declare(strict_types=1);

namespace App\Domain\TravelCost;

/** What the travel costs of a document were taken over from (migration 187). */
enum TravelCostBasis: string
{
    case Tour = 'tour';       // planned route of a tour (migration 182)
    case Planned = 'planned'; // planned trips of an event (migration 186)
    case Actual = 'actual';   // billable trips of the driver's logbook (migration 184)

    public function label(): string
    {
        return match ($this) {
            self::Tour => 'geplante Kilometer der Tour',
            self::Planned => 'geplante Kilometer',
            self::Actual => 'tatsächlich gefahrene Kilometer',
        };
    }

    public function isPlanned(): bool
    {
        return $this !== self::Actual;
    }
}
