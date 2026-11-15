<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Integration\Payment;

use App\Application\OnlinePayment\CheckoutRequest;
use App\Application\OnlinePayment\PaymentGatewayException;
use App\Domain\OnlinePayment\OnlinePaymentSettings;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use App\Infrastructure\Integration\Payment\PayPalGateway;
use App\Infrastructure\Integration\Payment\SumUpGateway;
use Closure;
use Codeception\Test\Unit;

/** PayPal/SumUp adapters against scripted HTTP responses (no network). */
final class PaymentGatewaysTest extends Unit
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    private array $requests = [];

    /** @var list<array{status: int, json: array<string, mixed>|null, raw: string}> */
    private array $responses = [];

    public function testPayPalCreatesOrderAndReturnsPayerActionLink(): void
    {
        $this->respond(200, ['access_token' => 'A21']);
        $this->respond(201, ['id' => 'ORDER1', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [
            ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER1'],
            ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER1'],
        ]]);

        $session = (new PayPalGateway($this->transport()))->createCheckout($this->settings(), $this->request());

        self::assertSame('ORDER1', $session->providerReference);
        self::assertSame('https://www.sandbox.paypal.com/checkoutnow?token=ORDER1', $session->redirectUrl);

        self::assertSame('https://api-m.sandbox.paypal.com/v1/oauth2/token', $this->requests[0]['url']);
        self::assertSame('Basic ' . base64_encode('cid:csecret'), $this->requests[0]['headers']['Authorization']);
        self::assertSame('grant_type=client_credentials', $this->requests[0]['body']);

        self::assertSame('https://api-m.sandbox.paypal.com/v2/checkout/orders', $this->requests[1]['url']);
        self::assertSame('Bearer A21', $this->requests[1]['headers']['Authorization']);
        $body = json_decode((string) $this->requests[1]['body'], true);
        self::assertSame('CAPTURE', $body['intent']);
        self::assertSame(['currency_code' => 'EUR', 'value' => '119.00'], $body['purchase_units'][0]['amount']);
        self::assertSame('https://x.example/return', $body['payment_source']['paypal']['experience_context']['return_url']);
    }

    public function testPayPalUsesLiveEndpointWithoutSandbox(): void
    {
        $this->respond(200, ['access_token' => 'A21']);
        $this->respond(201, ['id' => 'ORDER1', 'links' => [['rel' => 'approve', 'href' => 'https://www.paypal.com/approve']]]);

        $settings = $this->settings();
        $settings->paypalSandbox = false;
        (new PayPalGateway($this->transport()))->createCheckout($settings, $this->request());

        self::assertStringStartsWith('https://api-m.paypal.com/', $this->requests[1]['url']);
    }

    public function testPayPalCaptureCompleted(): void
    {
        $this->respond(200, ['access_token' => 'A21']);
        $this->respond(201, ['id' => 'ORDER1', 'status' => 'COMPLETED', 'purchase_units' => [[
            'payments' => ['captures' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'EUR', 'value' => '119.00']]]],
        ]]]);

        $result = (new PayPalGateway($this->transport()))->confirm($this->settings(), 'ORDER1');

        self::assertSame(OnlinePaymentStatus::Completed, $result->status);
        self::assertSame('119.00', $result->amount);
        self::assertSame('https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER1/capture', $this->requests[1]['url']);
        self::assertSame('{}', $this->requests[1]['body']);
    }

    public function testPayPalAlreadyCapturedOrderIsReadBack(): void
    {
        $this->respond(200, ['access_token' => 'A21']);
        $this->respond(422, ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]]);
        $this->respond(200, ['access_token' => 'A21']);
        $this->respond(200, ['id' => 'ORDER1', 'status' => 'COMPLETED', 'purchase_units' => [[
            'payments' => ['captures' => [['status' => 'COMPLETED', 'amount' => ['currency_code' => 'EUR', 'value' => '5.00']]]],
        ]]]);

        $result = (new PayPalGateway($this->transport()))->confirm($this->settings(), 'ORDER1');

        self::assertSame(OnlinePaymentStatus::Completed, $result->status);
        self::assertSame('GET', $this->requests[3]['method']);
    }

    public function testPayPalNotApprovedIsPending(): void
    {
        $this->respond(200, ['access_token' => 'A21']);
        $this->respond(422, ['details' => [['issue' => 'ORDER_NOT_APPROVED']]]);

        $result = (new PayPalGateway($this->transport()))->confirm($this->settings(), 'ORDER1');

        self::assertSame(OnlinePaymentStatus::Pending, $result->status);
    }

    public function testPayPalInvalidCredentialsThrow(): void
    {
        $this->respond(401, ['error' => 'invalid_client', 'error_description' => 'Client Authentication failed']);

        $this->expectException(PaymentGatewayException::class);
        (new PayPalGateway($this->transport()))->createCheckout($this->settings(), $this->request());
    }

    public function testSumUpCreatesHostedCheckout(): void
    {
        $this->respond(201, ['id' => 'chk_1', 'status' => 'PENDING', 'hosted_checkout_url' => 'https://checkout.sumup.com/pay/chk_1']);

        $session = (new SumUpGateway($this->transport()))->createCheckout($this->settings(), $this->request());

        self::assertSame('chk_1', $session->providerReference);
        self::assertSame('https://checkout.sumup.com/pay/chk_1', $session->redirectUrl);
        self::assertSame('https://api.sumup.com/v0.1/checkouts', $this->requests[0]['url']);
        self::assertSame('Bearer sup_sk_x', $this->requests[0]['headers']['Authorization']);
        $body = json_decode((string) $this->requests[0]['body'], true);
        self::assertSame('pay-1', $body['checkout_reference']);
        self::assertEquals(119.0, $body['amount']);
        self::assertSame('MCODE', $body['merchant_code']);
        self::assertSame('https://x.example/webhook', $body['return_url']);
        self::assertSame('https://x.example/return', $body['redirect_url']);
        self::assertTrue($body['hosted_checkout']['enabled']);
    }

    public function testSumUpStatusMapping(): void
    {
        $gateway = new SumUpGateway($this->transport());

        $this->respond(200, ['id' => 'chk_1', 'status' => 'PAID', 'amount' => 119, 'currency' => 'EUR']);
        $paid = $gateway->confirm($this->settings(), 'chk_1');
        self::assertSame(OnlinePaymentStatus::Completed, $paid->status);
        self::assertSame('119.00', $paid->amount);

        $this->respond(200, ['id' => 'chk_1', 'status' => 'PENDING']);
        self::assertSame(OnlinePaymentStatus::Pending, $gateway->confirm($this->settings(), 'chk_1')->status);

        $this->respond(200, ['id' => 'chk_1', 'status' => 'FAILED']);
        self::assertSame(OnlinePaymentStatus::Failed, $gateway->confirm($this->settings(), 'chk_1')->status);

        $this->respond(200, ['id' => 'chk_1', 'status' => 'EXPIRED']);
        self::assertSame(OnlinePaymentStatus::Cancelled, $gateway->confirm($this->settings(), 'chk_1')->status);

        self::assertSame('https://api.sumup.com/v0.1/checkouts/chk_1', $this->requests[0]['url']);
        self::assertNull($this->requests[0]['body']);
    }

    public function testSumUpErrorThrows(): void
    {
        $this->respond(400, ['message' => 'Validation error', 'error_code' => 'INVALID']);

        $this->expectException(PaymentGatewayException::class);
        (new SumUpGateway($this->transport()))->createCheckout($this->settings(), $this->request());
    }

    /** @param array<string, mixed> $json */
    private function respond(int $status, array $json): void
    {
        $this->responses[] = ['status' => $status, 'json' => $json, 'raw' => (string) json_encode($json)];
    }

    private function transport(): Closure
    {
        return function (string $method, string $url, array $headers, ?string $body): array {
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
            $response = array_shift($this->responses);
            self::assertNotNull($response, 'Unexpected HTTP request to ' . $url);

            return $response;
        };
    }

    private function settings(): OnlinePaymentSettings
    {
        return new OnlinePaymentSettings(
            paypalEnabled: true,
            paypalSandbox: true,
            paypalClientId: 'cid',
            paypalClientSecret: 'csecret',
            sumupEnabled: true,
            sumupApiKey: 'sup_sk_x',
            sumupMerchantCode: 'MCODE',
        );
    }

    private function request(): CheckoutRequest
    {
        return new CheckoutRequest(
            paymentId: 'pay-1',
            invoiceId: 'inv-1',
            invoiceNumber: 'RE-1',
            amount: '119.00',
            currency: 'EUR',
            description: 'Rechnung RE-1',
            returnUrl: 'https://x.example/return',
            cancelUrl: 'https://x.example/cancel',
            webhookUrl: 'https://x.example/webhook',
        );
    }
}
