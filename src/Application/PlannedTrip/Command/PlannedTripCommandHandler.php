<?php

declare(strict_types=1);

namespace App\Application\PlannedTrip\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\PlannedTrip\PlannedTripService;

/** Commands of the planned trips (migration 186), see PlannedTripService for the rules. */
final class PlannedTripCommandHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PlannedTripService $service)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SavePlannedTrip => $this->service->save($command->rentalId, $command->tripId, $command->tripDate, $command->from, $command->fromText, $command->to, $command->toText, $command->kmOverride),
            $command instanceof DeletePlannedTrip => $this->service->delete($command->rentalId, $command->tripId),
            $command instanceof RestoreDefaultPlannedTrips => $this->service->restoreDefaults($command->rentalId),
            $command instanceof RecalculatePlannedTrips => $this->service->recalculate($command->rentalId),
            default => throw new \InvalidArgumentException('Unsupported command ' . $command::class),
        };
    }
}
