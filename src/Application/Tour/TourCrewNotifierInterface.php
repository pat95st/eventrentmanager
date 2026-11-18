<?php

declare(strict_types=1);

namespace App\Application\Tour;

use App\Domain\Rental\Rental;

/** Tells a technician that they are no longer needed at some stops of a tour. */
interface TourCrewNotifierInterface
{
    /** @param string[] $stopLabels */
    public function notifyRemoved(Rental $tour, string $userId, array $stopLabels, ?string $replacementName): void;
}
