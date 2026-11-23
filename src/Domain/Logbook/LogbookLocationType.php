<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

/** Kind of start or destination of a trip (migration 184). */
enum LogbookLocationType: string
{
    case Warehouse = 'warehouse';         // ref: warehouse id
    case EventVenue = 'event_venue';      // ref: rental id (location of the assigned event)
    case TourStop = 'tour_stop';          // ref: tour stop / waypoint id
    case Company = 'company';             // company address
    case SavedAddress = 'saved_address';  // ref: tour_saved_addresses id
    case Venue = 'venue';                 // ref: venue id (master data)
    case Custom = 'custom';               // free address or text

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Lager',
            self::EventVenue => 'Veranstaltungsort',
            self::TourStop => 'Tourstop',
            self::Company => 'Firmenadresse',
            self::SavedAddress => 'Gespeicherte Adresse',
            self::Venue => 'Veranstaltungsort (Stammdaten)',
            self::Custom => 'Freie Adresse',
        };
    }

    /** Types that point to a record (the others need no reference id). */
    public function needsReference(): bool
    {
        return $this !== self::Company && $this !== self::Custom;
    }
}
