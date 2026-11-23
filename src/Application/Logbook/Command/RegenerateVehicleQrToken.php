<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandInterface;

/** Creates a new QR token for a vehicle (migration 185); printed codes stop working. */
final class RegenerateVehicleQrToken implements CommandInterface
{
    public function __construct(public readonly string $vehicleId)
    {
    }
}
