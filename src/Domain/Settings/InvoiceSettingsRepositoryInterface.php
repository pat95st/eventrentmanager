<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface InvoiceSettingsRepositoryInterface
{
    public function get(): InvoiceSettings;

    public function save(InvoiceSettings $settings): void;
}
