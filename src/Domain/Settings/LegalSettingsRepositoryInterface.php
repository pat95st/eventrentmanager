<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface LegalSettingsRepositoryInterface
{
    public function get(): LegalSettings;

    public function save(LegalSettings $settings): void;
}
