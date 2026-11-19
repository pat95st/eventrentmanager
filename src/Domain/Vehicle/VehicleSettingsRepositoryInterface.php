<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

interface VehicleSettingsRepositoryInterface
{
    public function get(): VehicleSettings;

    public function save(VehicleSettings $settings): void;
}
