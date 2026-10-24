<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandInterface;

final class UpdateVehicle implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $category,
        public readonly string $name,
        public readonly ?string $licensePlate,
        public readonly ?string $payloadKg,
        public readonly ?string $loadAreaDimensions,
        public readonly bool $towHitchAvailable,
        public readonly ?string $towCapacityKg,
        public readonly ?string $emptyWeightKg,
        public readonly ?string $notes = null,
    ) {
    }
}
