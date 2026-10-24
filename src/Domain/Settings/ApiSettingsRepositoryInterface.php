<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface ApiSettingsRepositoryInterface
{
    public function get(): ApiSettings;

    public function save(ApiSettings $settings): void;
}
