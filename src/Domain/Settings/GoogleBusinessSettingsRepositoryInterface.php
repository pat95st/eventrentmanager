<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface GoogleBusinessSettingsRepositoryInterface
{
    public function get(): GoogleBusinessSettings;

    public function save(GoogleBusinessSettings $settings): void;
}
