<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface VoucherSettingsRepositoryInterface
{
    public function get(): VoucherSettings;

    public function save(VoucherSettings $settings): void;
}
