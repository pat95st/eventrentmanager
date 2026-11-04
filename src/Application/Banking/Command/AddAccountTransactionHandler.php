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
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddAccountTransactionHandler implements CommandHandlerInterface
{
    use ValidatesAccountInput;

    public function __construct(
        private readonly AccountLedgerRepositoryInterface $ledger,
        private readonly BankAccountRepositoryInterface $accounts,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddAccountTransaction);
        $errors = [];
        $this->requireAccount($this->accounts, $command->bankAccountId, $errors);
        $date = $this->bookingDate($command->bookingDate, $errors);
        $cents = AccountAmount::parseCents($command->amount);
        if ($cents === null || $cents <= 0) {
            $errors['amount'] = 'Bitte einen Betrag größer 0 angeben.';
        }
        if (!in_array($command->direction, ['in', 'out'], true)) {
            $errors['direction'] = 'Bitte Eingang oder Ausgang wählen.';
        }
        $description = trim($command->description);
        if ($description === '') {
            $errors['description'] = 'Bitte einen Buchungstext angeben (z. B. Kontoführungsgebühr).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $transaction = new AccountTransaction(
            Uuid::v4(), $command->bankAccountId, $date,
            $command->direction === 'out' ? -$cents : $cents,
            AccountTransactionKind::Manual, $description,
            trim($command->counterparty) !== '' ? trim($command->counterparty) : null,
            null, null, null, $now, $now,
        );
        $this->ledger->save($transaction);

        return $transaction->id;
    }
}
