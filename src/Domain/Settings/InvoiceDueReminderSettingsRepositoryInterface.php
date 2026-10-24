<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface InvoiceDueReminderSettingsRepositoryInterface
{
    public function get(): InvoiceDueReminderSettings;

    public function save(InvoiceDueReminderSettings $settings): void;
}
