<?php

declare(strict_types=1);

namespace App\Domain\Tour;

/** Deviation of the crew at one stop from the tour's technicians. */
final class TourStopTechnician
{
    public function __construct(
        public readonly string $id,
        public readonly string $stopId,
        public readonly TourCrewAction $action,
        public readonly string $userId,
        public readonly ?string $replacesUserId = null,
    ) {
    }
}
