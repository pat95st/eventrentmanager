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
            'SELECT default_cost_per_km_auto, default_cost_per_km_trailer FROM vehicle_settings WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return new VehicleSettings();
        }

        return new VehicleSettings(
            (string) $row['default_cost_per_km_auto'],
            (string) $row['default_cost_per_km_trailer'],
        );
    }

    public function save(VehicleSettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO vehicle_settings (id, default_cost_per_km_auto, default_cost_per_km_trailer)
             VALUES (1, :auto, :trailer)
             ON DUPLICATE KEY UPDATE default_cost_per_km_auto = VALUES(default_cost_per_km_auto),
                                     default_cost_per_km_trailer = VALUES(default_cost_per_km_trailer)'
        )->execute([
            'auto' => $settings->defaultCostPerKmAuto,
            'trailer' => $settings->defaultCostPerKmTrailer,
        ]);
    }
}
