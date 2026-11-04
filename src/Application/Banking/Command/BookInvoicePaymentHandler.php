<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Banking\AccountAmount;
use App\Domain\Banking\AccountLedgerRepositoryInterface;
use App\Domain\Banking\AccountTransaction;
use App\Domain\Banking\AccountTransactionKind;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class BookInvoicePaymentHandler implements CommandHandlerInterface
{
    use ValidatesAccountInput;

    public function __construct(
        private readonly AccountLedgerRepositoryInterface $ledger,
        private readonly BankAccountRepositoryInterface $accounts,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly ?CustomerRepositoryInterface $customers = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof BookInvoicePayment);
        $errors = [];
        $this->requireAccount($this->accounts, $command->bankAccountId, $errors);
        $date = $this->bookingDate($command->bookingDate, $errors);
        $cents = AccountAmount::parseCents($command->amount);
        if ($cents === null || $cents <= 0) {
            $errors['amount'] = 'Bitte den gezahlten Betrag angeben.';
        }
        $invoice = $command->invoiceId !== '' ? $this->invoices->findById($command->invoiceId) : null;
        if ($invoice === null) {
            $errors['invoiceId'] = 'Bitte eine Rechnung auswählen.';
        } elseif (!in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Paid], true)) {
            $errors['invoiceId'] = 'Zahlungen lassen sich nur für versendete Rechnungen verbuchen.';
        } elseif ($this->ledger->findByInvoice($invoice->id) !== []) {
            $errors['invoiceId'] = 'Für diese Rechnung ist bereits eine Zahlung auf einem Konto verbucht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $customer = $invoice->customerId !== null ? $this->customers?->findById($invoice->customerId)?->displayName() : null;
        $now = new DateTimeImmutable();
        $transaction = new AccountTransaction(
            Uuid::v4(), $command->bankAccountId, $date, $cents,
            AccountTransactionKind::Invoice, 'Zahlung Rechnung ' . ($invoice->invoiceNumber ?? ''), $customer,
            null, $invoice->id, null, $now, $now,
        );
        $this->ledger->save($transaction);

        if ($invoice->status === InvoiceStatus::Sent) {
            $invoice->status = InvoiceStatus::Paid;
            $invoice->paidAt = $date;
            $invoice->updatedAt = $now;
            $this->invoices->save($invoice);
        }

        return $transaction->id;
    }
}
