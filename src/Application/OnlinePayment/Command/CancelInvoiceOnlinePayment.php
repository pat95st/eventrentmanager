<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandInterface;

/** The customer aborted the checkout at the provider (PayPal cancel URL). */
final class CancelInvoiceOnlinePayment implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $paymentId,
    ) {
    }
}
