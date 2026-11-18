<?php

declare(strict_types=1);

namespace App\Domain\Tour;

enum TourStopKind: string
{
    case Stop = 'stop';         // event at a venue
    case Waypoint = 'waypoint'; // stop between venues
    case Start = 'start';       // start of the route (migration 178)
    case End = 'end';           // destination of the route (migration 178)
}
