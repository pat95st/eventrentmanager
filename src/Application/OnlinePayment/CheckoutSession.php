<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

final class CheckoutSession
{
    public function __construct(
        public readonly string $providerReference,
        public readonly string $redirectUrl,
    ) {
    }
}
