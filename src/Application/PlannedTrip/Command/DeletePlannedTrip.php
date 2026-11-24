<?php

declare(strict_types=1);

namespace App\Application\PlannedTrip\Command;

use App\Application\Command\CommandInterface;

final class DeletePlannedTrip implements CommandInterface
{
    public function __construct(public readonly string $rentalId, public readonly string $tripId)
    {
    }
}
