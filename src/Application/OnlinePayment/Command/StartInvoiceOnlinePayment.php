<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandInterface;
use App\Domain\OnlinePayment\OnlinePaymentProvider;

/** Starts a checkout for the invoice behind $token; the handler returns the provider URL to redirect the customer to. */
final class StartInvoiceOnlinePayment implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly OnlinePaymentProvider $provider,
        public readonly ?string $fallbackBaseUrl = null,
    ) {
    }
}
