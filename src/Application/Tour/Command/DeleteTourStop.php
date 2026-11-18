<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Deletes a stop or waypoint. */
final class DeleteTourStop implements CommandInterface
{
    public function __construct(public readonly string $stopId)
    {
    }
}
