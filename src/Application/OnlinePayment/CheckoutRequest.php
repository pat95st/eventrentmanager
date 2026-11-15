<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

final class CheckoutRequest
{
    public function __construct(
        // Our OnlinePayment id — used as unique reference at the provider.
        public readonly string $paymentId,
        public readonly string $invoiceId,
        public readonly ?string $invoiceNumber,
        public readonly string $amount, // "123.45"
        public readonly string $currency,
        public readonly string $description,
        // Where the customer lands after paying / approving.
        public readonly string $returnUrl,
        // Where the customer lands after cancelling (PayPal only).
        public readonly string $cancelUrl,
        // Server-to-server notification URL (SumUp only).
        public readonly string $webhookUrl,
    ) {
    }
}
