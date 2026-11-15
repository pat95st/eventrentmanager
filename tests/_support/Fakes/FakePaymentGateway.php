<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\OnlinePayment\CheckoutRequest;
use App\Application\OnlinePayment\CheckoutSession;
use App\Application\OnlinePayment\GatewayResult;
use App\Application\OnlinePayment\PaymentGatewayException;
use App\Application\OnlinePayment\PaymentGatewayInterface;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\OnlinePayment\OnlinePaymentSettings;
use App\Domain\OnlinePayment\OnlinePaymentStatus;

/** Scriptable payment provider: records checkout requests and answers confirm() with $nextResult. */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    /** @var CheckoutRequest[] */
    public array $checkouts = [];
    public int $confirmCalls = 0;
    public ?GatewayResult $nextResult = null;
    public bool $failCheckout = false;

    public function __construct(private readonly OnlinePaymentProvider $provider)
    {
    }

    public function provider(): OnlinePaymentProvider
    {
        return $this->provider;
    }

    public function createCheckout(OnlinePaymentSettings $settings, CheckoutRequest $request): CheckoutSession
    {
        if ($this->failCheckout) {
            throw new PaymentGatewayException('provider down');
        }
        $this->checkouts[] = $request;

        return new CheckoutSession('REF-' . count($this->checkouts), 'https://pay.example/' . $this->provider->value . '/' . count($this->checkouts));
    }

    public function confirm(OnlinePaymentSettings $settings, string $providerReference): GatewayResult
    {
        $this->confirmCalls++;

        return $this->nextResult ?? new GatewayResult(OnlinePaymentStatus::Pending);
    }
}
