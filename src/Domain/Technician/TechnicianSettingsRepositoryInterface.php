<?php

declare(strict_types=1);

namespace App\Domain\Technician;

interface TechnicianSettingsRepositoryInterface
{
    public function get(): TechnicianSettings;

    public function save(TechnicianSettings $settings): void;
}
