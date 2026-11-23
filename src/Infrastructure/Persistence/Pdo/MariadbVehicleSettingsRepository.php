<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Vehicle\VehicleSettings;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use PDO;

final class MariadbVehicleSettingsRepository implements VehicleSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): VehicleSettings
    {
        $row = $this->pdo->query(
            'SELECT default_cost_per_km_auto, default_cost_per_km_trailer, travel_cost_markup_percent, event_travel_costs_default FROM vehicle_settings WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return new VehicleSettings();
        }

        return new VehicleSettings(
            (string) $row['default_cost_per_km_auto'],
            (string) $row['default_cost_per_km_trailer'],
            (string) $row['travel_cost_markup_percent'],
            (bool) ($row['event_travel_costs_default'] ?? false),
        );
    }

    public function save(VehicleSettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO vehicle_settings (id, default_cost_per_km_auto, default_cost_per_km_trailer, travel_cost_markup_percent, event_travel_costs_default)
             VALUES (1, :auto, :trailer, :markup, :event_default)
             ON DUPLICATE KEY UPDATE default_cost_per_km_auto = VALUES(default_cost_per_km_auto),
                                     default_cost_per_km_trailer = VALUES(default_cost_per_km_trailer),
                                     travel_cost_markup_percent = VALUES(travel_cost_markup_percent),
                                     event_travel_costs_default = VALUES(event_travel_costs_default)'
        )->execute([
            'auto' => $settings->defaultCostPerKmAuto,
            'trailer' => $settings->defaultCostPerKmTrailer,
            'markup' => $settings->travelCostMarkupPercent,
            'event_default' => (int) $settings->eventTravelCostsDefault,
        ]);
    }
}
