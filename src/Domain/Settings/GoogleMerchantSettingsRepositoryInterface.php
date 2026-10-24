<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface GoogleMerchantSettingsRepositoryInterface
{
    public function get(): GoogleMerchantSettings;

    public function save(GoogleMerchantSettings $settings): void;
}
