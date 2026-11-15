<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\OnlinePayment\PaymentGatewayException;
use App\Application\OnlinePayment\PaymentGatewayRegistry;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\OnlinePayment\OnlinePayment;
use App\Domain\OnlinePayment\OnlinePaymentRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use DateTimeImmutable;

/**
 * Idempotent: an already completed payment is returned unchanged, so the
 * return redirect and the webhook may both arrive (in any order). A payment
 * the customer cancelled or that failed is still re-checked — if the
 * provider reports it as paid after all, the money was received.
 *
 * Marks the invoice paid directly via the repository instead of
 * dispatching MarkInvoicePaid (no handler gets a bus injected, see
 * ConfirmBankImportHandler for the same pattern). An invoice that is no
 * longer "sent" (already paid manually, cancelled) is left untouched; the
 * payment is recorded nevertheless so the administrator sees it.
 */
final class ConfirmInvoiceOnlinePaymentHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly OnlinePaymentRepositoryInterface $payments,
        private readonly OnlinePaymentSettingsRepositoryInterface $settings,
        private readonly PaymentGatewayRegistry $gateways,
        private readonly InvoiceRepositoryInterface $invoices,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ConfirmInvoiceOnlinePayment);

        $payment = $this->findPayment($command);

        if ($payment->status === OnlinePaymentStatus::Completed) {
            return $payment;
        }
        if ($payment->providerReference === null) {
            // The checkout was never created at the provider.
            return $payment;
        }

        try {
            $result = $this->gateways->get($payment->provider)->confirm($this->settings->get(), $payment->providerReference);
        } catch (PaymentGatewayException $e) {
            // Keep the payment pending; the webhook or a later return can confirm it.
            error_log('[Materialverleih] Online payment could not be confirmed: ' . $e->getMessage());

            return $payment;
        }

        $now = new DateTimeImmutable();

        if ($result->status === OnlinePaymentStatus::Completed) {
            if (!$this->amountMatches($payment, $result->amount, $result->currency)) {
                $payment->markFailed(OnlinePaymentStatus::Failed, sprintf(
                    'Gezahlter Betrag (%s %s) weicht vom erwarteten Betrag (%s %s) ab — bitte manuell prüfen.',
                    $result->amount ?? '?',
                    $result->currency ?? '?',
                    $payment->amount,
                    $payment->currency,
                ), $now);
                $this->payments->save($payment);

                return $payment;
            }

            $payment->markCompleted($now);
            $this->payments->save($payment);
            $this->markInvoicePaid($payment->invoiceId, $now);

            return $payment;
        }

        if ($result->status->isFinal()) {
            $payment->markFailed($result->status, $result->message, $now);
            $this->payments->save($payment);
        }

        return $payment;
    }

    private function findPayment(ConfirmInvoiceOnlinePayment $command): OnlinePayment
    {
        if ($command->paymentId !== null) {
            $payment = $this->payments->findById($command->paymentId);
            // A reference passed along (e.g. PayPal's ?token=) must belong to this payment.
            $referenceMismatch = $payment !== null && $command->providerReference !== null
                && ($payment->provider !== $command->provider || $payment->providerReference !== $command->providerReference);
            if ($referenceMismatch) {
                $payment = null;
            }
        } elseif ($command->provider !== null && $command->providerReference !== null) {
            $payment = $this->payments->findByProviderReference($command->provider, $command->providerReference);
        } else {
            $payment = null;
        }

        if ($payment === null) {
            throw new NotFoundException('Zahlung wurde nicht gefunden.');
        }

        return $payment;
    }

    private function amountMatches(OnlinePayment $payment, ?string $amount, ?string $currency): bool
    {
        if ($amount === null) {
            return true; // provider did not report an amount; the checkout was created for exactly $payment->amount
        }
        if ($currency !== null && strtoupper($currency) !== strtoupper($payment->currency)) {
            return false;
        }

        return abs((float) $amount - (float) $payment->amount) < 0.005;
    }

    private function markInvoicePaid(string $invoiceId, DateTimeImmutable $now): void
    {
        $invoice = $this->invoices->findById($invoiceId);
        if ($invoice === null || $invoice->status !== InvoiceStatus::Sent) {
            return;
        }

        $invoice->status = InvoiceStatus::Paid;
        $invoice->paidAt = $now;
        $invoice->updatedAt = $now;
        $this->invoices->save($invoice);
    }
}
