<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Banking\AccountLedgerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Receipt\ReceiptStatus;
use DateTimeImmutable;

/**
 * Buchung löschen (Migration 131). War sie die einzige Zahlung zu einem
 * Beleg bzw. einer Rechnung, wird dieser wieder auf offen gesetzt.
 */
final class DeleteAccountTransactionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly AccountLedgerRepositoryInterface $ledger,
        private readonly ?ReceiptRepositoryInterface $receipts = null,
        private readonly ?InvoiceRepositoryInterface $invoices = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof DeleteAccountTransaction);
        $transaction = $this->ledger->findById($command->id);
        if ($transaction === null) {
            throw new NotFoundException('Die Buchung wurde nicht gefunden.');
        }
        $this->ledger->delete($transaction->id);
        $now = new DateTimeImmutable();

        if ($transaction->receiptId !== null && $this->receipts !== null && $this->ledger->findByReceipt($transaction->receiptId) === []) {
            $receipt = $this->receipts->findById($transaction->receiptId);
            if ($receipt !== null && $receipt->status === ReceiptStatus::Paid) {
                $receipt->status = ReceiptStatus::Open;
                $receipt->paidAt = null;
                $receipt->updatedAt = $now;
                $this->receipts->save($receipt);
            }
        }
        if ($transaction->invoiceId !== null && $this->invoices !== null && $this->ledger->findByInvoice($transaction->invoiceId) === []) {
            $invoice = $this->invoices->findById($transaction->invoiceId);
            if ($invoice !== null && $invoice->status === InvoiceStatus::Paid) {
                $invoice->status = InvoiceStatus::Sent;
                $invoice->paidAt = null;
                $invoice->updatedAt = $now;
                $this->invoices->save($invoice);
            }
        }

        return $transaction->bankAccountId;
    }
}
