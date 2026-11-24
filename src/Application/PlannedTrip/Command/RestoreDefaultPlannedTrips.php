<?php

declare(strict_types=1);

namespace App\Application\PlannedTrip\Command;

use App\Application\Command\CommandInterface;

/** Recreates the outbound and return trip of an event (migration 186). */
final class RestoreDefaultPlannedTrips implements CommandInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
