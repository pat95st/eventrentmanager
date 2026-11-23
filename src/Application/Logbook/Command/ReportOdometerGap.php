<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandInterface;

/**
 * A logged-in driver reports that trips are missing (migration 185): the
 * current reading lies above the last recorded one. Returns the id of the
 * "unclear" trip that closes the gap.
 */
final class ReportOdometerGap implements CommandInterface
{
    public function __construct(
        public readonly string $vehicleId,
        public readonly string $actorUserId,
        public readonly int $currentReading,
    ) {
    }
}
