<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\OnlinePayment\CheckoutRequest;
use App\Application\OnlinePayment\InvoicePaymentUrlBuilder;
use App\Application\OnlinePayment\PayableInvoiceLookupInterface;
use App\Application\OnlinePayment\PaymentGatewayException;
use App\Application\OnlinePayment\PaymentGatewayRegistry;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\OnlinePayment\InvoicePaymentLinkRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePayment;
use App\Domain\OnlinePayment\OnlinePaymentRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Creates a pending OnlinePayment for the current amount due, opens a
 * checkout at the chosen provider and returns its redirect URL. The amount
 * is always computed server-side — nothing the customer submits influences it.
 */
final class StartInvoiceOnlinePaymentHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InvoicePaymentLinkRepositoryInterface $links,
        private readonly PayableInvoiceLookupInterface $invoices,
        private readonly OnlinePaymentSettingsRepositoryInterface $settings,
        private readonly OnlinePaymentRepositoryInterface $payments,
        private readonly PaymentGatewayRegistry $gateways,
        private readonly InvoicePaymentUrlBuilder $urls,
        private readonly CompanySettingsRepositoryInterface $companySettings,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof StartInvoiceOnlinePayment);

        $invoiceId = $this->links->findInvoiceIdByToken($command->token);
        $invoice = $invoiceId !== null ? $this->invoices->find($invoiceId) : null;
        if ($invoice === null) {
            throw new NotFoundException('Zahlungslink ist ungültig.');
        }
        if (!$invoice->isPayable()) {
            throw new ConflictException('Diese Rechnung kann nicht (mehr) online bezahlt werden.');
        }

        $settings = $this->settings->get();
        if (!$settings->isProviderUsable($command->provider)) {
            throw new ValidationException(['provider' => 'Diese Zahlungsart steht derzeit nicht zur Verfügung.']);
        }

        $now = new DateTimeImmutable();
        $payment = new OnlinePayment(
            id: Uuid::v4(),
            invoiceId: $invoice->invoiceId,
            provider: $command->provider,
            providerReference: null,
            amount: $invoice->amountDue,
            currency: $settings->currency,
            status: OnlinePaymentStatus::Pending,
            failureReason: null,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->payments->save($payment);

        $companyName = trim((string) $this->companySettings->get()->name);
        $description = 'Rechnung ' . ($invoice->invoiceNumber ?? $invoice->invoiceId) . ($companyName !== '' ? ' – ' . $companyName : '');

        try {
            $session = $this->gateways->get($command->provider)->createCheckout($settings, new CheckoutRequest(
                paymentId: $payment->id,
                invoiceId: $invoice->invoiceId,
                invoiceNumber: $invoice->invoiceNumber,
                amount: $payment->amount,
                currency: $payment->currency,
                description: mb_substr($description, 0, 120),
                returnUrl: $this->urls->returnUrl($command->token, $command->provider, $payment->id, $command->fallbackBaseUrl),
                cancelUrl: $this->urls->cancelUrl($command->token, $payment->id, $command->fallbackBaseUrl),
                webhookUrl: $this->urls->sumUpWebhookUrl($command->fallbackBaseUrl),
            ));
        } catch (PaymentGatewayException $e) {
            error_log('[Materialverleih] Online payment could not be started: ' . $e->getMessage());
            $payment->markFailed(OnlinePaymentStatus::Failed, $e->getMessage(), new DateTimeImmutable());
            $this->payments->save($payment);

            throw new ValidationException(['provider' => 'Die Zahlung konnte beim Zahlungsanbieter nicht gestartet werden. Bitte versuchen Sie es später erneut oder wählen Sie eine andere Zahlungsart.']);
        }

        $payment->providerReference = $session->providerReference;
        $payment->updatedAt = new DateTimeImmutable();
        $this->payments->save($payment);

        return $session->redirectUrl;
    }
}
