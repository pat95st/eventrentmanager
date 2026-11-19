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
        public readonly ?string $loadAreaLengthCm,
        public readonly ?string $loadAreaWidthCm,
        public readonly ?string $loadAreaHeightCm,
        public readonly bool $loadAreaOpenTop,
        public readonly bool $towHitchAvailable,
        public readonly ?string $towCapacityKg,
        public readonly ?string $emptyWeightKg,
        public readonly ?string $notes = null,
        public readonly ?string $acquisitionYear = null,
        public readonly ?string $purchasePrice = null,
        public readonly ?string $currentValue = null,
        // Migration 178: truck routing
        public readonly ?string $heightCm = null,
        public readonly ?string $widthCm = null,
        public readonly ?string $lengthCm = null,
        public readonly ?string $grossWeightKg = null,
        // Migration 179: empty = default of the category from the settings
        public readonly ?string $costPerKm = null,
    ) {
    }
}
