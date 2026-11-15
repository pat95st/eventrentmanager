<?php

declare(strict_types=1);

namespace App\Domain\OnlinePayment;

interface OnlinePaymentSettingsRepositoryInterface
{
    public function get(): OnlinePaymentSettings;

    public function save(OnlinePaymentSettings $settings): void;
}
