<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandInterface;
use App\Domain\OnlinePayment\OnlinePaymentProvider;

/**
 * Asks the provider for the final state of a payment and — if it was paid —
 * marks the payment completed and the invoice paid. Either identified by our
 * payment id (customer returns from the provider) or by the provider's
 * reference (webhook). The handler returns the resulting OnlinePayment.
 */
final class ConfirmInvoiceOnlinePayment implements CommandInterface
{
    private function __construct(
        public readonly ?string $paymentId,
        public readonly ?OnlinePaymentProvider $provider,
        public readonly ?string $providerReference,
    ) {
    }

    public static function byPaymentId(string $paymentId, ?OnlinePaymentProvider $provider = null, ?string $providerReference = null): self
    {
        return new self($paymentId, $provider, $providerReference);
    }

    public static function byProviderReference(OnlinePaymentProvider $provider, string $providerReference): self
    {
        return new self(null, $provider, $providerReference);
    }
}
