<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Creates a minute program for a single stop (named after the stop, starting at its event start). */
final class CreateTourStopMinuteProgram implements CommandInterface
{
    public function __construct(public readonly string $stopId)
    {
    }
}
