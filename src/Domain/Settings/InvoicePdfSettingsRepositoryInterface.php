<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface InvoicePdfSettingsRepositoryInterface
{
    public function get(): InvoicePdfSettings;

    public function save(InvoicePdfSettings $settings): void;
}
