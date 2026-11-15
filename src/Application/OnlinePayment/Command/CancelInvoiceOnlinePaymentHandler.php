<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\OnlinePayment\InvoicePaymentLinkRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use DateTimeImmutable;

/** Only touches a pending payment that belongs to the invoice behind $token. */
final class CancelInvoiceOnlinePaymentHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InvoicePaymentLinkRepositoryInterface $links,
        private readonly OnlinePaymentRepositoryInterface $payments,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CancelInvoiceOnlinePayment);

        $invoiceId = $this->links->findInvoiceIdByToken($command->token);
        $payment = $this->payments->findById($command->paymentId);
        if ($invoiceId === null || $payment === null || $payment->invoiceId !== $invoiceId) {
            return null;
        }

        if ($payment->status === OnlinePaymentStatus::Pending) {
            $payment->markFailed(OnlinePaymentStatus::Cancelled, 'Vom Kunden abgebrochen.', new DateTimeImmutable());
            $this->payments->save($payment);
        }

        return null;
    }
}
