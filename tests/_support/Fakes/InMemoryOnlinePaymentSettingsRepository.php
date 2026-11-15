<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\OnlinePayment\OnlinePaymentSettings;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;

final class InMemoryOnlinePaymentSettingsRepository implements OnlinePaymentSettingsRepositoryInterface
{
    public function __construct(public OnlinePaymentSettings $settings = new OnlinePaymentSettings())
    {
    }

    public function get(): OnlinePaymentSettings
    {
        return clone $this->settings;
    }

    public function save(OnlinePaymentSettings $settings): void
    {
        $this->settings = clone $settings;
    }
}
