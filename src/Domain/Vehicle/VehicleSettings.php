<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/**
 * Fleet-wide vehicle settings (migration 181): default cost per kilometre
 * per category. A vehicle without its own value (Vehicle::$costPerKm = null)
 * uses the default of its category. Amounts are strings like "0.30" (see
 * Vehicle for why decimals are kept as strings). The markup on travel
 * costs (migration 182) is kept here as well.
 */
final class VehicleSettings
{
    public const DEFAULT_COST_PER_KM_AUTO = '0.30';
    public const DEFAULT_COST_PER_KM_TRAILER = '0.15';

    public function __construct(
        public string $defaultCostPerKmAuto = self::DEFAULT_COST_PER_KM_AUTO,
        public string $defaultCostPerKmTrailer = self::DEFAULT_COST_PER_KM_TRAILER,
        // Migration 182: markup in percent on the cost per km when travel
        // costs of a tour are billed (e.g. "10.00"); "0.00" = billed at cost.
        public string $travelCostMarkupPercent = '0.00',
    ) {
    }

    /**
     * Price per km billed to the customer for a cost per km: cost plus
     * markup, rounded to whole cents.
     */
    public function billedRatePerKm(float $costPerKm): string
    {
        return number_format(round($costPerKm * (1 + ((float) $this->travelCostMarkupPercent) / 100), 2), 2, '.', '');
    }

    public function defaultCostPerKm(VehicleCategory $category): string
    {
        return match ($category) {
            VehicleCategory::Auto => $this->defaultCostPerKmAuto,
            VehicleCategory::Anhaenger => $this->defaultCostPerKmTrailer,
        };
    }
}
