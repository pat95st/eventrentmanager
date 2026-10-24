<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface CompanySettingsRepositoryInterface
{
    public function get(): CompanySettings;

    public function save(CompanySettings $settings): void;
}
