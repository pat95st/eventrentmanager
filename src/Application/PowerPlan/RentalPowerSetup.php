<?php

declare(strict_types=1);

namespace App\Application\PowerPlan;

use App\Domain\PowerPlan\PowerNode;
use App\Domain\PowerPlan\RentalPowerPlan;
use App\Domain\Rental\Rental;
use App\Domain\Venue\PowerConnection;

/** Everything the power plan of an event is calculated from (see RentalPowerSetupLoader). */
final class RentalPowerSetup
{
    /**
     * @param PowerNode[] $nodes distributors first, then consumers (in planning order)
     * @param PowerConnection[] $venueConnections
     */
    public function __construct(
        public readonly Rental $rental,
        public readonly RentalPowerPlan $plan,
        public readonly array $nodes,
        public readonly array $venueConnections,
        public readonly int $unitsWithoutPower,
    ) {
    }

    public function node(string $key): ?PowerNode
    {
        foreach ($this->nodes as $node) {
            if ($node->key() === $key) {
                return $node;
            }
        }

        return null;
    }

    public function venueConnection(string $id): ?PowerConnection
    {
        foreach ($this->venueConnections as $connection) {
            if ($connection->id === $id) {
                return $connection;
            }
        }

        return null;
    }
}
