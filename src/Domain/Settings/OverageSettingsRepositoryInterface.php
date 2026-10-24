<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface OverageSettingsRepositoryInterface
{
    public function get(): OverageSettings;

    public function save(OverageSettings $settings): void;
}
