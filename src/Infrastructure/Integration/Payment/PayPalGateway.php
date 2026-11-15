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
 * PayPal Checkout via the Orders API v2 (redirect flow, no JavaScript SDK):
 *  1. OAuth2 client-credentials token (POST /v1/oauth2/token)
 *  2. create order with intent CAPTURE, customer is redirected to the
 *     "payer-action" link
 *  3. PayPal redirects back to return_url?token=<order id>; we capture the
 *     order (POST /v2/checkout/orders/{id}/capture). Capturing twice is
 *     harmless: ORDER_ALREADY_CAPTURED is answered by reading the order.
 */
final class PayPalGateway implements PaymentGatewayInterface
{
    private const LIVE_URL = 'https://api-m.paypal.com';
    private const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';

    /** @var Closure(string, string, array<string, string>, ?string): array{status: int, json: array<string, mixed>|null, raw: string} */
    private readonly Closure $transport;

    /** @param (Closure(string, string, array<string, string>, ?string): array{status: int, json: array<string, mixed>|null, raw: string})|null $transport injectable for tests */
    public function __construct(?Closure $transport = null)
    {
        $this->transport = $transport ?? static fn (string $method, string $url, array $headers, ?string $body): array => HttpJsonRequest::sendRaw($method, $url, $headers, $body);
    }

    public function provider(): OnlinePaymentProvider
    {
        return OnlinePaymentProvider::PayPal;
    }

    public function createCheckout(OnlinePaymentSettings $settings, CheckoutRequest $request): CheckoutSession
    {
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $request->paymentId,
                'custom_id' => $request->invoiceId,
                'description' => $request->description,
                'amount' => [
                    'currency_code' => $request->currency,
                    'value' => $request->amount,
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'return_url' => $request->returnUrl,
                        'cancel_url' => $request->cancelUrl,
                        'user_action' => 'PAY_NOW',
                        'shipping_preference' => 'NO_SHIPPING',
                    ],
                ],
            ],
        ];

        $response = $this->request($settings, 'POST', '/v2/checkout/orders', $body, ['PayPal-Request-Id' => 'order-' . $request->paymentId]);
        if ($response['status'] < 200 || $response['status'] >= 300 || !isset($response['json']['id'])) {
            throw new PaymentGatewayException('PayPal-Bestellung konnte nicht angelegt werden: ' . $this->errorText($response));
        }

        $redirect = null;
        foreach ((array) ($response['json']['links'] ?? []) as $link) {
            if (is_array($link) && in_array($link['rel'] ?? null, ['payer-action', 'approve'], true)) {
                $redirect = (string) $link['href'];
                break;
            }
        }
        if ($redirect === null) {
            throw new PaymentGatewayException('PayPal hat keinen Freigabe-Link geliefert.');
        }

        return new CheckoutSession((string) $response['json']['id'], $redirect);
    }

    public function confirm(OnlinePaymentSettings $settings, string $providerReference): GatewayResult
    {
        $orderPath = '/v2/checkout/orders/' . rawurlencode($providerReference);
        $response = $this->request($settings, 'POST', $orderPath . '/capture', null, ['PayPal-Request-Id' => 'capture-' . $providerReference]);

        if ($response['status'] >= 200 && $response['status'] < 300 && is_array($response['json'])) {
            return $this->resultFromOrder($response['json']);
        }

        $issue = $this->issue($response);
        if ($response['status'] === 422 && $issue === 'ORDER_ALREADY_CAPTURED') {
            $order = $this->request($settings, 'GET', $orderPath, null);
            if ($order['status'] === 200 && is_array($order['json'])) {
                return $this->resultFromOrder($order['json']);
            }
        }
        if ($response['status'] === 422 && in_array($issue, ['ORDER_NOT_APPROVED', 'PAYER_ACTION_REQUIRED'], true)) {
            return new GatewayResult(OnlinePaymentStatus::Pending, message: 'Zahlung wurde bei PayPal noch nicht freigegeben.');
        }
        if ($response['status'] === 422 && in_array($issue, ['INSTRUMENT_DECLINED', 'TRANSACTION_REFUSED', 'ORDER_EXPIRED', 'ORDER_COMPLETION_IN_PROGRESS'], true)) {
            return $issue === 'ORDER_COMPLETION_IN_PROGRESS'
                ? new GatewayResult(OnlinePaymentStatus::Pending, message: 'PayPal verarbeitet die Zahlung noch.')
                : new GatewayResult(OnlinePaymentStatus::Failed, message: 'PayPal: ' . $issue);
        }
        if ($response['status'] === 404) {
            return new GatewayResult(OnlinePaymentStatus::Failed, message: 'PayPal-Bestellung existiert nicht (mehr).');
        }

        throw new PaymentGatewayException('PayPal-Zahlung konnte nicht abgeschlossen werden: ' . $this->errorText($response));
    }

    /** @param array<string, mixed> $order */
    private function resultFromOrder(array $order): GatewayResult
    {
        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
        $orderStatus = (string) ($order['status'] ?? '');

        if (is_array($capture)) {
            $amount = isset($capture['amount']['value']) ? (string) $capture['amount']['value'] : null;
            $currency = isset($capture['amount']['currency_code']) ? (string) $capture['amount']['currency_code'] : null;

            return match ((string) ($capture['status'] ?? '')) {
                'COMPLETED' => new GatewayResult(OnlinePaymentStatus::Completed, $amount, $currency),
                'PENDING' => new GatewayResult(OnlinePaymentStatus::Pending, $amount, $currency, 'Zahlung ist bei PayPal noch in Prüfung.'),
                default => new GatewayResult(OnlinePaymentStatus::Failed, $amount, $currency, 'PayPal-Zahlung: ' . (string) ($capture['status'] ?? 'unbekannt')),
            };
        }

        return match ($orderStatus) {
            'VOIDED' => new GatewayResult(OnlinePaymentStatus::Cancelled, message: 'PayPal-Bestellung wurde verworfen.'),
            default => new GatewayResult(OnlinePaymentStatus::Pending, message: 'PayPal-Status: ' . $orderStatus),
        };
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array<string, mixed>|null, raw: string}
     */
    private function request(OnlinePaymentSettings $settings, string $method, string $path, ?array $jsonBody, array $extraHeaders = []): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken($settings),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Prefer' => 'return=representation',
        ] + $extraHeaders;

        $body = $method === 'GET' ? null : ($jsonBody !== null ? json_encode($jsonBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '{}');

        return $this->send($method, $this->baseUrl($settings) . $path, $headers, $body);
    }

    private function accessToken(OnlinePaymentSettings $settings): string
    {
        if ($settings->paypalClientId === null || $settings->paypalClientSecret === null) {
            throw new PaymentGatewayException('PayPal-Zugangsdaten fehlen.');
        }

        $response = $this->send('POST', $this->baseUrl($settings) . '/v1/oauth2/token', [
            'Authorization' => 'Basic ' . base64_encode($settings->paypalClientId . ':' . $settings->paypalClientSecret),
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ], 'grant_type=client_credentials');

        $token = $response['json']['access_token'] ?? null;
        if ($response['status'] !== 200 || !is_string($token) || $token === '') {
            throw new PaymentGatewayException('PayPal-Anmeldung fehlgeschlagen (Client-ID/Secret bzw. Sandbox-Einstellung prüfen): ' . $this->errorText($response));
        }

        return $token;
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, json: array<string, mixed>|null, raw: string}
     */
    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        try {
            return ($this->transport)($method, $url, $headers, $body);
        } catch (RuntimeException $e) {
            throw new PaymentGatewayException($e->getMessage(), 0, $e);
        }
    }

    private function baseUrl(OnlinePaymentSettings $settings): string
    {
        return $settings->paypalSandbox ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /** @param array{status: int, json: array<string, mixed>|null, raw: string} $response */
    private function issue(array $response): ?string
    {
        $issue = $response['json']['details'][0]['issue'] ?? null;

        return is_string($issue) ? $issue : null;
    }

    /** @param array{status: int, json: array<string, mixed>|null, raw: string} $response */
    private function errorText(array $response): string
    {
        $json = $response['json'] ?? [];
        $text = $this->issue($response)
            ?? (is_string($json['message'] ?? null) ? $json['message'] : null)
            ?? (is_string($json['error_description'] ?? null) ? $json['error_description'] : null)
            ?? mb_substr($response['raw'], 0, 200);

        return 'HTTP ' . $response['status'] . ' – ' . $text;
    }
}
