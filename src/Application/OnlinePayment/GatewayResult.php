<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

use App\Domain\OnlinePayment\OnlinePaymentStatus;

final class GatewayResult
{
    public function __construct(
        public readonly OnlinePaymentStatus $status,
        // Amount/currency actually paid as reported by the provider ("123.45"),
        // null if the provider does not report it for this state.
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $message = null,
    ) {
    }
}
