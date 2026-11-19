<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/**
 * Fleet-wide vehicle settings (migration 179): default cost per kilometre
 * per category. A vehicle without its own value (Vehicle::$costPerKm = null)
 * uses the default of its category. Amounts are strings like "0.30" (see
 * Vehicle for why decimals are kept as strings).
 */
final class VehicleSettings
{
    public const DEFAULT_COST_PER_KM_AUTO = '0.30';
    public const DEFAULT_COST_PER_KM_TRAILER = '0.15';

    public function __construct(
        public string $defaultCostPerKmAuto = self::DEFAULT_COST_PER_KM_AUTO,
        public string $defaultCostPerKmTrailer = self::DEFAULT_COST_PER_KM_TRAILER,
    ) {
    }

    public function defaultCostPerKm(VehicleCategory $category): string
    {
        return match ($category) {
            VehicleCategory::Auto => $this->defaultCostPerKmAuto,
            VehicleCategory::Anhaenger => $this->defaultCostPerKmTrailer,
        };
    }
}
