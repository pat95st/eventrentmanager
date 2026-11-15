<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

use App\Domain\Exception\ValidationException;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;

/**
 * Builds the absolute URLs of the public payment page and the provider
 * callbacks from the configured base URL (Settings > Integrations), like
 * RentalCustomerChecklistUrlBuilder. A request-derived fallback can be given
 * for calls made during an HTTP request; mails always need the configured
 * base URL.
 */
final class InvoicePaymentUrlBuilder
{
    public function __construct(private readonly IntegrationBaseSettingsRepositoryInterface $baseSettings)
    {
    }

    public function hasConfiguredBaseUrl(): bool
    {
        $baseUrl = $this->baseSettings->get()->baseUrl;

        return $baseUrl !== null && trim($baseUrl) !== '';
    }

    /** @throws ValidationException if neither a base URL is configured nor a fallback given */
    public function pageUrl(string $token, ?string $fallbackBaseUrl = null): string
    {
        return $this->base($fallbackBaseUrl) . '/zahlung/' . rawurlencode($token);
    }

    public function returnUrl(string $token, OnlinePaymentProvider $provider, string $paymentId, ?string $fallbackBaseUrl = null): string
    {
        return $this->pageUrl($token, $fallbackBaseUrl) . '/' . $provider->value . '/zurueck?zahlung=' . rawurlencode($paymentId);
    }

    public function cancelUrl(string $token, string $paymentId, ?string $fallbackBaseUrl = null): string
    {
        return $this->pageUrl($token, $fallbackBaseUrl) . '/abbrechen?zahlung=' . rawurlencode($paymentId);
    }

    public function sumUpWebhookUrl(?string $fallbackBaseUrl = null): string
    {
        return $this->base($fallbackBaseUrl) . '/api/payments/sumup/webhook';
    }

    private function base(?string $fallbackBaseUrl): string
    {
        $baseUrl = $this->baseSettings->get()->baseUrl;
        if ($baseUrl === null || trim($baseUrl) === '') {
            $baseUrl = $fallbackBaseUrl;
        }
        if ($baseUrl === null || trim($baseUrl) === '') {
            throw new ValidationException([
                'base_url' => 'Es ist keine Basis-URL dieser Installation hinterlegt (siehe Einstellungen > Integrationen) — ohne sie kann kein von außen erreichbarer Zahlungslink gebaut werden.',
            ]);
        }

        return rtrim(trim($baseUrl), '/');
    }
}
