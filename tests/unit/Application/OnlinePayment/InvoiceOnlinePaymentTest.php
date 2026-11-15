<?php

declare(strict_types=1);

namespace Tests\Unit\Application\OnlinePayment;

use App\Application\OnlinePayment\Command\CancelInvoiceOnlinePayment;
use App\Application\OnlinePayment\Command\CancelInvoiceOnlinePaymentHandler;
use App\Application\OnlinePayment\Command\ConfirmInvoiceOnlinePayment;
use App\Application\OnlinePayment\Command\ConfirmInvoiceOnlinePaymentHandler;
use App\Application\OnlinePayment\Command\StartInvoiceOnlinePayment;
use App\Application\OnlinePayment\Command\StartInvoiceOnlinePaymentHandler;
use App\Application\OnlinePayment\Command\UpdateOnlinePaymentSettings;
use App\Application\OnlinePayment\Command\UpdateOnlinePaymentSettingsHandler;
use App\Application\OnlinePayment\GatewayResult;
use App\Application\OnlinePayment\InvoicePaymentLinkProvider;
use App\Application\OnlinePayment\InvoicePaymentUrlBuilder;
use App\Application\OnlinePayment\PaymentGatewayRegistry;
use App\Application\OnlinePayment\Query\GetInvoicePaymentPage;
use App\Application\OnlinePayment\Query\GetInvoicePaymentPageHandler;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\OnlinePayment\OnlinePayment;
use App\Domain\OnlinePayment\OnlinePaymentProvider;
use App\Domain\OnlinePayment\OnlinePaymentSettings;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use App\Domain\Settings\IntegrationBaseSettings;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\FakePayableInvoiceLookup;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\Support\Fakes\InMemoryCompanySettingsRepository;
use Tests\Support\Fakes\InMemoryInvoicePaymentLinkRepository;
use Tests\Support\Fakes\InMemoryInvoiceRepository;
use Tests\Support\Fakes\InMemoryOnlinePaymentRepository;
use Tests\Support\Fakes\InMemoryOnlinePaymentSettingsRepository;

final class InvoiceOnlinePaymentTest extends Unit
{
    private InMemoryInvoiceRepository $invoices;
    private InMemoryInvoicePaymentLinkRepository $links;
    private InMemoryOnlinePaymentRepository $payments;
    private InMemoryOnlinePaymentSettingsRepository $settings;
    private FakePayableInvoiceLookup $lookup;
    private FakePaymentGateway $paypal;
    private FakePaymentGateway $sumup;
    private InvoicePaymentLinkProvider $linkProvider;
    private StartInvoiceOnlinePaymentHandler $start;
    private ConfirmInvoiceOnlinePaymentHandler $confirm;
    private ?string $baseUrl = 'https://verleih.example';

    protected function _before(): void
    {
        $this->invoices = new InMemoryInvoiceRepository();
        $this->links = new InMemoryInvoicePaymentLinkRepository();
        $this->payments = new InMemoryOnlinePaymentRepository();
        $this->settings = new InMemoryOnlinePaymentSettingsRepository(new OnlinePaymentSettings(
            paypalEnabled: true,
            paypalClientId: 'client',
            paypalClientSecret: 'secret',
            sumupEnabled: true,
            sumupApiKey: 'sup_sk_test',
            sumupMerchantCode: 'MTEST',
        ));
        $this->lookup = new FakePayableInvoiceLookup($this->invoices);
        $this->paypal = new FakePaymentGateway(OnlinePaymentProvider::PayPal);
        $this->sumup = new FakePaymentGateway(OnlinePaymentProvider::SumUp);
        $registry = new PaymentGatewayRegistry($this->paypal, $this->sumup);

        $test = $this;
        $baseSettings = new class ($test) implements IntegrationBaseSettingsRepositoryInterface {
            public function __construct(private readonly InvoiceOnlinePaymentTest $test)
            {
            }

            public function get(): IntegrationBaseSettings
            {
                return new IntegrationBaseSettings($this->test->baseUrl());
            }

            public function save(IntegrationBaseSettings $settings): void
            {
            }
        };
        $urls = new InvoicePaymentUrlBuilder($baseSettings);

        $this->linkProvider = new InvoicePaymentLinkProvider($this->links, $this->settings, $urls);
        $this->start = new StartInvoiceOnlinePaymentHandler($this->links, $this->lookup, $this->settings, $this->payments, $registry, $urls, new InMemoryCompanySettingsRepository());
        $this->confirm = new ConfirmInvoiceOnlinePaymentHandler($this->payments, $this->settings, $registry, $this->invoices);
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function testPaymentLinkIsStableAndUnguessable(): void
    {
        $this->sentInvoice('inv-1');

        $url = $this->linkProvider->publicUrlFor('inv-1');
        self::assertNotNull($url);
        self::assertMatchesRegularExpression('#^https://verleih\.example/zahlung/[0-9a-f]{48}$#', $url);
        self::assertSame($url, $this->linkProvider->publicUrlFor('inv-1'));
    }

    public function testNoPaymentLinkWithoutUsableProviderOrBaseUrl(): void
    {
        $this->sentInvoice('inv-1');

        $this->baseUrl = null;
        self::assertNull($this->linkProvider->publicUrlFor('inv-1'));
        self::assertNotNull($this->linkProvider->publicUrlFor('inv-1', 'https://fallback.example'));

        $this->baseUrl = 'https://verleih.example';
        $this->settings->settings = new OnlinePaymentSettings(paypalEnabled: true, paypalClientId: 'client'); // secret missing
        self::assertNull($this->linkProvider->publicUrlFor('inv-1'));
    }

    public function testStartCreatesPendingPaymentForServerSideAmount(): void
    {
        $token = $this->sentInvoice('inv-1', '119.00');

        $redirect = $this->start->handle(new StartInvoiceOnlinePayment($token, OnlinePaymentProvider::PayPal));

        self::assertSame('https://pay.example/paypal/1', $redirect);
        $request = $this->paypal->checkouts[0];
        self::assertSame('119.00', $request->amount);
        self::assertSame('EUR', $request->currency);
        self::assertStringStartsWith('https://verleih.example/zahlung/' . $token . '/paypal/zurueck?zahlung=', $request->returnUrl);
        self::assertSame('https://verleih.example/api/payments/sumup/webhook', $request->webhookUrl);

        $payment = $this->payments->findById($request->paymentId);
        self::assertNotNull($payment);
        self::assertSame(OnlinePaymentStatus::Pending, $payment->status);
        self::assertSame('REF-1', $payment->providerReference);
        self::assertSame('inv-1', $payment->invoiceId);
    }

    public function testStartRejectsUnknownTokenDraftsAndPaidInvoices(): void
    {
        try {
            $this->start->handle(new StartInvoiceOnlinePayment('nope', OnlinePaymentProvider::PayPal));
            self::fail('Expected NotFoundException');
        } catch (NotFoundException) {
        }

        $token = $this->sentInvoice('inv-1', '50.00');
        $this->invoices->invoices['inv-1']->status = InvoiceStatus::Paid;
        $this->expectException(ConflictException::class);
        $this->start->handle(new StartInvoiceOnlinePayment($token, OnlinePaymentProvider::PayPal));
    }

    public function testStartRejectsDisabledProvider(): void
    {
        $token = $this->sentInvoice('inv-1', '50.00');
        $this->settings->settings->sumupEnabled = false;

        $this->expectException(ValidationException::class);
        $this->start->handle(new StartInvoiceOnlinePayment($token, OnlinePaymentProvider::SumUp));
    }

    public function testProviderErrorMarksPaymentFailed(): void
    {
        $token = $this->sentInvoice('inv-1', '50.00');
        $this->sumup->failCheckout = true;

        try {
            $this->start->handle(new StartInvoiceOnlinePayment($token, OnlinePaymentProvider::SumUp));
            self::fail('Expected ValidationException');
        } catch (ValidationException) {
        }

        $all = $this->payments->findByInvoice('inv-1');
        self::assertCount(1, $all);
        self::assertSame(OnlinePaymentStatus::Failed, $all[0]->status);
    }

    public function testConfirmMarksPaymentAndInvoicePaidAndIsIdempotent(): void
    {
        $paymentId = $this->startedPayment(OnlinePaymentProvider::PayPal, '119.00');
        $this->paypal->nextResult = new GatewayResult(OnlinePaymentStatus::Completed, '119.00', 'EUR');

        $payment = $this->confirm->handle(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId, OnlinePaymentProvider::PayPal, 'REF-1'));

        self::assertSame(OnlinePaymentStatus::Completed, $payment->status);
        self::assertSame(InvoiceStatus::Paid, $this->invoices->invoices['inv-1']->status);
        self::assertNotNull($this->invoices->invoices['inv-1']->paidAt);

        // Webhook/second return: no further provider call.
        $again = $this->confirm->handle(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId));
        self::assertSame(OnlinePaymentStatus::Completed, $again->status);
        self::assertSame(1, $this->paypal->confirmCalls);
    }

    public function testConfirmRejectsForeignProviderReference(): void
    {
        $paymentId = $this->startedPayment(OnlinePaymentProvider::PayPal, '10.00');

        $this->expectException(NotFoundException::class);
        $this->confirm->handle(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId, OnlinePaymentProvider::PayPal, 'OTHER-ORDER'));
    }

    public function testAmountMismatchDoesNotMarkInvoicePaid(): void
    {
        $paymentId = $this->startedPayment(OnlinePaymentProvider::SumUp, '119.00');
        $this->sumup->nextResult = new GatewayResult(OnlinePaymentStatus::Completed, '1.00', 'EUR');

        $payment = $this->confirm->handle(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId));

        self::assertSame(OnlinePaymentStatus::Failed, $payment->status);
        self::assertStringContainsString('weicht', (string) $payment->failureReason);
        self::assertSame(InvoiceStatus::Sent, $this->invoices->invoices['inv-1']->status);
    }

    public function testWebhookConfirmsByProviderReference(): void
    {
        $this->startedPayment(OnlinePaymentProvider::SumUp, '42.00');
        $this->sumup->nextResult = new GatewayResult(OnlinePaymentStatus::Completed, '42.00', 'EUR');

        $payment = $this->confirm->handle(ConfirmInvoiceOnlinePayment::byProviderReference(OnlinePaymentProvider::SumUp, 'REF-1'));

        self::assertSame(OnlinePaymentStatus::Completed, $payment->status);
        self::assertSame(InvoiceStatus::Paid, $this->invoices->invoices['inv-1']->status);
    }

    public function testPendingResultKeepsPaymentPendingAndFailureIsRecorded(): void
    {
        $paymentId = $this->startedPayment(OnlinePaymentProvider::SumUp, '42.00');

        $payment = $this->confirm->handle(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId));
        self::assertSame(OnlinePaymentStatus::Pending, $payment->status);

        $this->sumup->nextResult = new GatewayResult(OnlinePaymentStatus::Failed, message: 'declined');
        $payment = $this->confirm->handle(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId));
        self::assertSame(OnlinePaymentStatus::Failed, $payment->status);
        self::assertSame(InvoiceStatus::Sent, $this->invoices->invoices['inv-1']->status);
    }

    public function testCancelledPaymentIsStillCompletedIfProviderReportsPaid(): void
    {
        $paymentId = $this->startedPayment(OnlinePaymentProvider::PayPal, '20.00');
        $token = $this->links->tokens['inv-1'];

        (new CancelInvoiceOnlinePaymentHandler($this->links, $this->payments))->handle(new CancelInvoiceOnlinePayment($token, $paymentId));
        self::assertSame(OnlinePaymentStatus::Cancelled, $this->payments->findById($paymentId)?->status);

        $this->paypal->nextResult = new GatewayResult(OnlinePaymentStatus::Completed, '20.00', 'EUR');
        $payment = $this->confirm->handle(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId));
        self::assertSame(OnlinePaymentStatus::Completed, $payment->status);
    }

    public function testCancelIgnoresPaymentOfAnotherInvoice(): void
    {
        $paymentId = $this->startedPayment(OnlinePaymentProvider::PayPal, '20.00');
        $otherToken = $this->sentInvoice('inv-2', '5.00');

        (new CancelInvoiceOnlinePaymentHandler($this->links, $this->payments))->handle(new CancelInvoiceOnlinePayment($otherToken, $paymentId));

        self::assertSame(OnlinePaymentStatus::Pending, $this->payments->findById($paymentId)?->status);
    }

    public function testManuallyPaidInvoiceIsNotTouchedButPaymentRecorded(): void
    {
        $paymentId = $this->startedPayment(OnlinePaymentProvider::PayPal, '20.00');
        $paidAt = new DateTimeImmutable('2026-01-01 10:00:00');
        $this->invoices->invoices['inv-1']->status = InvoiceStatus::Paid;
        $this->invoices->invoices['inv-1']->paidAt = $paidAt;
        $this->paypal->nextResult = new GatewayResult(OnlinePaymentStatus::Completed, '20.00', 'EUR');

        $payment = $this->confirm->handle(ConfirmInvoiceOnlinePayment::byPaymentId($paymentId));

        self::assertSame(OnlinePaymentStatus::Completed, $payment->status);
        self::assertSame($paidAt, $this->invoices->invoices['inv-1']->paidAt);
    }

    public function testPaymentPageHidesDraftsAndListsUsableProviders(): void
    {
        $handler = new GetInvoicePaymentPageHandler($this->links, $this->lookup, $this->settings, $this->payments, new InMemoryCompanySettingsRepository());

        $token = $this->sentInvoice('inv-1', '10.00');
        $page = $handler->handle(new GetInvoicePaymentPage($token));
        self::assertSame([OnlinePaymentProvider::PayPal, OnlinePaymentProvider::SumUp], $page->providers);
        self::assertTrue($page->invoice->isPayable());

        $this->invoices->invoices['inv-1']->status = InvoiceStatus::Draft;
        $this->expectException(NotFoundException::class);
        $handler->handle(new GetInvoicePaymentPage($token));
    }

    public function testSettingsKeepStoredSecretsWhenLeftEmpty(): void
    {
        $handler = new UpdateOnlinePaymentSettingsHandler($this->settings);

        $handler->handle(new UpdateOnlinePaymentSettings(true, false, 'new-client', '', true, null, 'MNEW', 'eur'));

        $stored = $this->settings->get();
        self::assertSame('secret', $stored->paypalClientSecret);
        self::assertSame('sup_sk_test', $stored->sumupApiKey);
        self::assertSame('new-client', $stored->paypalClientId);
        self::assertFalse($stored->paypalSandbox);
        self::assertSame('EUR', $stored->currency);
    }

    public function testSettingsRequireCredentialsForEnabledProvider(): void
    {
        $this->settings->settings = new OnlinePaymentSettings();
        $handler = new UpdateOnlinePaymentSettingsHandler($this->settings);

        try {
            $handler->handle(new UpdateOnlinePaymentSettings(true, true, '', '', true, '', '', 'EUR'));
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('paypal_client_id', $e->errors());
            self::assertArrayHasKey('paypal_client_secret', $e->errors());
            self::assertArrayHasKey('sumup_api_key', $e->errors());
            self::assertArrayHasKey('sumup_merchant_code', $e->errors());
        }
        self::assertFalse($this->settings->get()->paypalEnabled);
    }

    private function sentInvoice(string $id, string $amount = '10.00'): string
    {
        $now = new DateTimeImmutable();
        $this->invoices->save(new Invoice(
            id: $id,
            customerId: null,
            eventLocation: null,
            venueId: null,
            startDate: null,
            endDate: null,
            invoiceNumber: 'RE-' . $id,
            invoiceDate: $now,
            dueDate: $now->modify('+14 days'),
            buyerReference: null,
            status: InvoiceStatus::Sent,
            isSmallBusiness: false,
            vatRatePercent: '19.00',
            paidAt: null,
            note: null,
            sourceOfferId: null,
            sourceRentalId: null,
            createdAt: $now,
            updatedAt: $now,
        ));
        $this->lookup->amounts[$id] = $amount;

        return $this->linkProvider->tokenFor($id);
    }

    private function startedPayment(OnlinePaymentProvider $provider, string $amount): string
    {
        $token = $this->sentInvoice('inv-1', $amount);
        $this->start->handle(new StartInvoiceOnlinePayment($token, $provider));
        $gateway = $provider === OnlinePaymentProvider::PayPal ? $this->paypal : $this->sumup;

        return $gateway->checkouts[0]->paymentId;
    }
}
