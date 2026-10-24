<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface AiAdImageSettingsRepositoryInterface
{
    public function get(): AiAdImageSettings;

    public function save(AiAdImageSettings $settings): void;
}
