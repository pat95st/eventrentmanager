<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface IntegrationBaseSettingsRepositoryInterface
{
    public function get(): IntegrationBaseSettings;

    public function save(IntegrationBaseSettings $settings): void;
}
