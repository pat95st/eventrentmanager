<?php

declare(strict_types=1);

namespace App\Domain\Tour;

/** Deviation of the material at one stop from the tour's base list. */
final class TourStopItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $stopId,
        public readonly string $articleId,
        public int $delta, // + additional, - fewer pieces
        public TourAllocation $allocation = TourAllocation::StopsOnly,
        public ?string $issueWaypointId = null,
        public ?string $returnWaypointId = null,
    ) {
    }
}
