<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Removes one crew deviation of a stop. */
final class DeleteTourCrewDeviation implements CommandInterface
{
    public function __construct(public readonly string $rentalId, public readonly string $deviationId)
    {
    }
}
