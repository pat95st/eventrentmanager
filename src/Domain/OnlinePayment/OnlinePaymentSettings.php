<?php

declare(strict_types=1);

namespace App\Domain\OnlinePayment;

/**
 * API credentials of the payment providers (migration 163).
 *
 * PayPal: REST app credentials (client id + secret) from the PayPal
 * developer dashboard, either for the sandbox or the live environment.
 * SumUp: an API key (or OAuth access token) plus the merchant code of the
 * SumUp account the money is paid to.
 */
final class OnlinePaymentSettings
{
    public function __construct(
        public bool $paypalEnabled = false,
        public bool $paypalSandbox = true,
        public ?string $paypalClientId = null,
        public ?string $paypalClientSecret = null,
        public bool $sumupEnabled = false,
        public ?string $sumupApiKey = null,
        public ?string $sumupMerchantCode = null,
        public string $currency = 'EUR',
    ) {
    }

    public function isPayPalUsable(): bool
    {
        return $this->paypalEnabled && $this->paypalClientId !== null && $this->paypalClientSecret !== null;
    }

    public function isSumUpUsable(): bool
    {
        return $this->sumupEnabled && $this->sumupApiKey !== null && $this->sumupMerchantCode !== null;
    }

    public function isProviderUsable(OnlinePaymentProvider $provider): bool
    {
        return match ($provider) {
            OnlinePaymentProvider::PayPal => $this->isPayPalUsable(),
            OnlinePaymentProvider::SumUp => $this->isSumUpUsable(),
        };
    }

    /** @return OnlinePaymentProvider[] */
    public function usableProviders(): array
    {
        return array_values(array_filter(
            OnlinePaymentProvider::cases(),
            fn (OnlinePaymentProvider $provider): bool => $this->isProviderUsable($provider),
        ));
    }

    public function isAnyProviderUsable(): bool
    {
        return $this->usableProviders() !== [];
    }
}
