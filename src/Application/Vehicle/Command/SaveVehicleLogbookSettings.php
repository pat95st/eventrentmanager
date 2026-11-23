<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandInterface;

/** Logbook settings of one vehicle (migration 183), see SaveVehicleLogbookSettingsHandler. */
final class SaveVehicleLogbookSettings implements CommandInterface
{
    public function __construct(
        public readonly string $vehicleId,
        public readonly bool $qrEnabled,
        public readonly bool $anonymousAllowed,
        public readonly bool $anonymousPrivateAllowed,
        public readonly bool $anonymousRequiresApproval,
        public readonly bool $odometerCheck,
        public readonly ?string $odometerToleranceKm,
        public readonly ?string $odometerInitialKm,
    ) {
    }
}
