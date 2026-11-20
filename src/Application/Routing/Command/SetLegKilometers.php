<?php

declare(strict_types=1);

namespace App\Application\Routing\Command;

use App\Application\Command\CommandInterface;

/** Kilometres of the drive to a tour stop entered by hand; empty = calculated distance (migration 182). */
final class SetLegKilometers implements CommandInterface
{
    public function __construct(public readonly string $stopId, public readonly string $kilometers)
    {
    }
}
