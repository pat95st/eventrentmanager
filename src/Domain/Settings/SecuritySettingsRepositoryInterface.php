<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface SecuritySettingsRepositoryInterface
{
    public function get(): SecuritySettings;

    public function save(SecuritySettings $settings): void;
}
