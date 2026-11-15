<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

use App\Domain\OnlinePayment\OnlinePaymentProvider;
use RuntimeException;

final class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    public function __construct(PaymentGatewayInterface ...$gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->provider()->value] = $gateway;
        }
    }

    public function get(OnlinePaymentProvider $provider): PaymentGatewayInterface
    {
        return $this->gateways[$provider->value]
            ?? throw new RuntimeException('No payment gateway registered for ' . $provider->value . '.');
    }
}
