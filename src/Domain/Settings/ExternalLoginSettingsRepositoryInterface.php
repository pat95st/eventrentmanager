<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface ExternalLoginSettingsRepositoryInterface
{
    public function get(): ExternalLoginSettings;

    public function save(ExternalLoginSettings $settings): void;
}
