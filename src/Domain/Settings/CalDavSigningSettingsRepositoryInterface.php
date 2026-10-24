<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface CalDavSigningSettingsRepositoryInterface
{
    public function get(): CalDavSigningSettings;

    public function save(CalDavSigningSettings $settings): void;
}
