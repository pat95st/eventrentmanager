<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface RentalReminderSettingsRepositoryInterface
{
    public function get(): RentalReminderSettings;

    public function save(RentalReminderSettings $settings): void;
}
