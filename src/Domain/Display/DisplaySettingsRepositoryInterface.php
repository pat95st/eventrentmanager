<?php

declare(strict_types=1);

namespace App\Domain\Display;

interface DisplaySettingsRepositoryInterface
{
    public function get(): DisplaySettings;

    public function save(DisplaySettings $settings): void;
}
