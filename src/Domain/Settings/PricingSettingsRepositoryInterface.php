<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface PricingSettingsRepositoryInterface
{
    public function get(): PricingSettings;

    public function save(PricingSettings $settings): void;
}
