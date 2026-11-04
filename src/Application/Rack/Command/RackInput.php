<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

/** Formularwerte eines Racks (gemeinsam für CreateRack/UpdateRack). */
final class RackInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $prefix,
        public readonly ?string $manufacturerId,
        public readonly ?string $heightUnits,
        public readonly ?string $installDepthCm,
        public readonly ?string $railType,
        public readonly ?string $width,
        public readonly ?string $color,
        public readonly bool $hasShockMount,
        public readonly bool $hasWheels,
        public readonly ?string $description,
    ) {
    }
}
