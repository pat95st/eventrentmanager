<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

/** Kind of a logbook trip (migration 184). */
enum LogbookCategory: string
{
    case Event = 'event';       // trip for an event, rental required
    case Business = 'business'; // business trip without event, purpose required
    case Private = 'private';   // private trip, no locations required
    case Unclear = 'unclear';   // gap in the odometer chain, to be clarified

    public function label(): string
    {
        return match ($this) {
            self::Event => 'Veranstaltung',
            self::Business => 'Geschäftlich',
            self::Private => 'Privat',
            self::Unclear => 'Ungeklärt',
        };
    }

    public function isBusiness(): bool
    {
        return $this === self::Event || $this === self::Business;
    }

    /** Categories a driver can choose when recording a trip. */
    public static function selectable(): array
    {
        return [self::Event, self::Business, self::Private];
    }
}
