<?php

declare(strict_types=1);

namespace App\Domain\Push;

interface PushSettingsRepositoryInterface
{
    public function get(): PushSettings;

    public function save(PushSettings $settings): void;
}
