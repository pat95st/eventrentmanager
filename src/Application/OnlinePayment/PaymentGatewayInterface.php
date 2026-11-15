<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\OnlinePayment\OnlinePaymentSettings;

/**
 * Adapter to one payment provider (PayPal, SumUp). Implementations live in
 * App\Infrastructure\Integration\Payment and talk to the provider's REST API.
 */
interface PaymentGatewayInterface
{
    public function provider(): OnlinePaymentProvider;

    /**
     * Creates an order/checkout at the provider and returns where the
     * customer has to be redirected to.
     *
     * @throws PaymentGatewayException
     */
    public function createCheckout(OnlinePaymentSettings $settings, CheckoutRequest $request): CheckoutSession;

    /**
     * Determines the final state of a checkout. For PayPal this captures an
     * approved order (idempotent — an already captured order is reported as
     * completed), for SumUp it reads the checkout status.
     *
     * @throws PaymentGatewayException
     */
    public function confirm(OnlinePaymentSettings $settings, string $providerReference): GatewayResult;
}
