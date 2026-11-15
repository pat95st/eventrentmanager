<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

use App\Domain\Exception\ValidationException;
use App\Domain\OnlinePayment\InvoicePaymentLinkRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;

/**
 * Hands out the (lazily created) secret token of an invoice's payment page
 * and its public URL. Used by the invoice detail page and the invoice mail.
 */
final class InvoicePaymentLinkProvider
{
    public function __construct(
        private readonly InvoicePaymentLinkRepositoryInterface $links,
        private readonly OnlinePaymentSettingsRepositoryInterface $settings,
        private readonly InvoicePaymentUrlBuilder $urls,
    ) {
    }

    public function tokenFor(string $invoiceId): string
    {
        $token = $this->links->findTokenForInvoice($invoiceId);
        if ($token !== null) {
            return $token;
        }

        $token = bin2hex(random_bytes(24));
        $this->links->save($invoiceId, $token);

        return $token;
    }

    /**
     * Public payment page URL, or null if online payment is not set up (no
     * usable provider or no base URL). Does not check the invoice status.
     */
    public function publicUrlFor(string $invoiceId, ?string $fallbackBaseUrl = null): ?string
    {
        if (!$this->settings->get()->isAnyProviderUsable()) {
            return null;
        }

        try {
            return $this->urls->pageUrl($this->tokenFor($invoiceId), $fallbackBaseUrl);
        } catch (ValidationException) {
            return null;
        }
    }
}
