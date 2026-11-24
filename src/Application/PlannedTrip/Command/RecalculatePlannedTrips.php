<?php

declare(strict_types=1);

namespace App\Application\PlannedTrip\Command;

use App\Application\Command\CommandInterface;

/** Calculates the driving distances of all planned trips of an event again (migration 186). */
final class RecalculatePlannedTrips implements CommandInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
