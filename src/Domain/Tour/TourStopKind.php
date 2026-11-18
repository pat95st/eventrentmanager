<?php

declare(strict_types=1);

namespace App\Domain\Tour;

enum TourStopKind: string
{
    case Stop = 'stop';         // event at a venue
    case Waypoint = 'waypoint'; // stop between venues
}
