<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface SmtpSettingsRepositoryInterface
{
    public function get(): SmtpSettings;

    public function save(SmtpSettings $settings): void;
}
