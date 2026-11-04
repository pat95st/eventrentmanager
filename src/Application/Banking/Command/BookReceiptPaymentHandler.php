<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Banking\AccountAmount;
use App\Domain\Banking\AccountLedgerRepositoryInterface;
use App\Domain\Banking\AccountTransaction;
use App\Domain\Banking\AccountTransactionKind;
use App\Domain\Exception\ValidationException;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Receipt\ReceiptStatus;
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class BookReceiptPaymentHandler implements CommandHandlerInterface
{
    use ValidatesAccountInput;

    public function __construct(
        private readonly AccountLedgerRepositoryInterface $ledger,
        private readonly BankAccountRepositoryInterface $accounts,
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly ?SupplierRepositoryInterface $suppliers = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof BookReceiptPayment);
        $errors = [];
        $this->requireAccount($this->accounts, $command->bankAccountId, $errors);
        $date = $this->bookingDate($command->bookingDate, $errors);
        $receipt = $command->receiptId !== '' ? $this->receipts->findById($command->receiptId) : null;
        if ($receipt === null) {
            $errors['receiptId'] = 'Bitte einen Beleg auswählen.';
        } elseif ($this->ledger->findByReceipt($receipt->id) !== []) {
            $errors['receiptId'] = 'Dieser Beleg wurde bereits von einem Konto abgebucht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $supplier = $this->suppliers?->findById($receipt->supplierId)?->name;
        $description = 'Beleg' . ($receipt->receiptNumber !== null && $receipt->receiptNumber !== '' ? ' ' . $receipt->receiptNumber : '')
            . ' vom ' . $receipt->receiptDate->format('d.m.Y');
        $now = new DateTimeImmutable();
        $transaction = new AccountTransaction(
            Uuid::v4(), $command->bankAccountId, $date,
            -abs(AccountAmount::fromDecimal($receipt->amount)),
            AccountTransactionKind::Receipt, $description, $supplier,
            $receipt->id, null, null, $now, $now,
        );
        $this->ledger->save($transaction);

        if ($receipt->status !== ReceiptStatus::Paid) {
            $receipt->status = ReceiptStatus::Paid;
            $receipt->paidAt = $date;
            $receipt->updatedAt = $now;
            $this->receipts->save($receipt);
        }

        return $transaction->id;
    }
}
