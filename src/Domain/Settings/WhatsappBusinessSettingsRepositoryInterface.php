<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface WhatsappBusinessSettingsRepositoryInterface
{
    public function get(): WhatsappBusinessSettings;

    public function save(WhatsappBusinessSettings $settings): void;
}
