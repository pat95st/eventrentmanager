<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Query;

use App\Application\OnlinePayment\PayableInvoiceLookupInterface;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\OnlinePayment\InvoicePaymentLinkRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;
use App\Domain\OnlinePayment\OnlinePaymentStatus;
use App\Domain\Settings\CompanySettingsRepositoryInterface;

/** Unknown tokens, drafts and cancelled invoices all yield the same NotFoundException (nothing to learn from the outside). */
final class GetInvoicePaymentPageHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InvoicePaymentLinkRepositoryInterface $links,
        private readonly PayableInvoiceLookupInterface $invoices,
        private readonly OnlinePaymentSettingsRepositoryInterface $settings,
        private readonly OnlinePaymentRepositoryInterface $payments,
        private readonly CompanySettingsRepositoryInterface $companySettings,
    ) {
    }

    public function handle(QueryInterface $query): InvoicePaymentPage
    {
        assert($query instanceof GetInvoicePaymentPage);

        $invoiceId = $this->links->findInvoiceIdByToken($query->token);
        $invoice = $invoiceId !== null ? $this->invoices->find($invoiceId) : null;
        if ($invoice === null || !in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Paid], true)) {
            throw new NotFoundException('Zahlungslink ist ungültig.');
        }

        $completed = null;
        foreach ($this->payments->findByInvoice($invoice->invoiceId) as $payment) {
            if ($payment->status === OnlinePaymentStatus::Completed) {
                $completed = $payment;
                break;
            }
        }

        $settings = $this->settings->get();

        return new InvoicePaymentPage(
            invoice: $invoice,
            providers: $settings->usableProviders(),
            currency: $settings->currency,
            companyName: trim((string) $this->companySettings->get()->name),
            completedPayment: $completed,
        );
    }
}
