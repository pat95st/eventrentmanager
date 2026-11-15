<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Payment;

use App\Application\OnlinePayment\CheckoutRequest;
use App\Application\OnlinePayment\CheckoutSession;
use App\Application\OnlinePayment\GatewayResult;
use App\Application\OnlinePayment\PaymentGatewayException;
use App\Application\OnlinePayment\PaymentGatewayInterface;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\OnlinePayment\OnlinePaymentSettings;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use App\Infrastructure\Integration\HttpJsonRequest;
use Closure;
use RuntimeException;

/**
 * SumUp online payments via the Checkouts API with SumUp's hosted payment
 * page (card, Apple Pay, Google Pay …):
 *  1. POST /v0.1/checkouts with hosted_checkout enabled; the customer is
 *     redirected to hosted_checkout_url
 *  2. after paying SumUp sends the customer to redirect_url and notifies
 *     return_url (our webhook) — both only trigger a status lookup
 *     (GET /v0.1/checkouts/{id}); the webhook body itself is never trusted.
 */
final class SumUpGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'https://api.sumup.com';

    /** @var Closure(string, string, array<string, string>, ?string): array{status: int, json: array<string, mixed>|null, raw: string} */
    private readonly Closure $transport;

    /** @param (Closure(string, string, array<string, string>, ?string): array{status: int, json: array<string, mixed>|null, raw: string})|null $transport injectable for tests */
    public function __construct(?Closure $transport = null)
    {
        $this->transport = $transport ?? static fn (string $method, string $url, array $headers, ?string $body): array => HttpJsonRequest::sendRaw($method, $url, $headers, $body);
    }

    public function provider(): OnlinePaymentProvider
    {
        return OnlinePaymentProvider::SumUp;
    }

    public function createCheckout(OnlinePaymentSettings $settings, CheckoutRequest $request): CheckoutSession
    {
        $body = [
            'checkout_reference' => $request->paymentId,
            'amount' => round((float) $request->amount, 2),
            'currency' => $request->currency,
            'merchant_code' => (string) $settings->sumupMerchantCode,
            'description' => $request->description,
            'return_url' => $request->webhookUrl,
            'redirect_url' => $request->returnUrl,
            'hosted_checkout' => ['enabled' => true],
        ];

        $response = $this->send($settings, 'POST', '/v0.1/checkouts', json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $json = $response['json'] ?? [];
        if ($response['status'] < 200 || $response['status'] >= 300 || !isset($json['id'])) {
            throw new PaymentGatewayException('SumUp-Checkout konnte nicht angelegt werden: ' . $this->errorText($response));
        }

        $url = $json['hosted_checkout_url'] ?? null;
        if (!is_string($url) || $url === '') {
            throw new PaymentGatewayException('SumUp hat keine Bezahlseite geliefert (Hosted Checkout für das Konto aktiviert?).');
        }

        return new CheckoutSession((string) $json['id'], $url);
    }

    public function confirm(OnlinePaymentSettings $settings, string $providerReference): GatewayResult
    {
        $response = $this->send($settings, 'GET', '/v0.1/checkouts/' . rawurlencode($providerReference), null);
        if ($response['status'] === 404) {
            return new GatewayResult(OnlinePaymentStatus::Failed, message: 'SumUp-Checkout existiert nicht (mehr).');
        }
        $json = $response['json'];
        if ($response['status'] !== 200 || !is_array($json)) {
            throw new PaymentGatewayException('SumUp-Status konnte nicht abgefragt werden: ' . $this->errorText($response));
        }

        $amount = isset($json['amount']) && is_numeric($json['amount']) ? number_format((float) $json['amount'], 2, '.', '') : null;
        $currency = isset($json['currency']) ? (string) $json['currency'] : null;

        return match (strtoupper((string) ($json['status'] ?? ''))) {
            'PAID' => new GatewayResult(OnlinePaymentStatus::Completed, $amount, $currency),
            'FAILED' => new GatewayResult(OnlinePaymentStatus::Failed, $amount, $currency, 'Zahlung bei SumUp fehlgeschlagen.'),
            'EXPIRED' => new GatewayResult(OnlinePaymentStatus::Cancelled, $amount, $currency, 'SumUp-Checkout ist abgelaufen.'),
            default => new GatewayResult(OnlinePaymentStatus::Pending, $amount, $currency),
        };
    }

    /** @return array{status: int, json: array<string, mixed>|null, raw: string} */
    private function send(OnlinePaymentSettings $settings, string $method, string $path, ?string $body): array
    {
        if ($settings->sumupApiKey === null) {
            throw new PaymentGatewayException('SumUp-API-Schlüssel fehlt.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $settings->sumupApiKey,
            'Accept' => 'application/json',
        ];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        try {
            return ($this->transport)($method, self::BASE_URL . $path, $headers, $body);
        } catch (RuntimeException $e) {
            throw new PaymentGatewayException($e->getMessage(), 0, $e);
        }
    }

    /** @param array{status: int, json: array<string, mixed>|null, raw: string} $response */
    private function errorText(array $response): string
    {
        $json = $response['json'] ?? [];
        $text = (is_string($json['message'] ?? null) ? $json['message'] : null)
            ?? (is_string($json['error_message'] ?? null) ? $json['error_message'] : null)
            ?? (is_string($json[0]['message'] ?? null) ? $json[0]['message'] : null)
            ?? mb_substr($response['raw'], 0, 200);

        return 'HTTP ' . $response['status'] . ' – ' . $text;
    }
}
