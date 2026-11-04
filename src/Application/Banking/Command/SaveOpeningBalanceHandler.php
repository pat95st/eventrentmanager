<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Banking\AccountAmount;
use App\Domain\Banking\AccountLedgerRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\BankAccountRepositoryInterface;

final class SaveOpeningBalanceHandler implements CommandHandlerInterface
{
    use ValidatesAccountInput;

    public function __construct(
        private readonly AccountLedgerRepositoryInterface $ledger,
        private readonly BankAccountRepositoryInterface $accounts,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveOpeningBalance);
        $errors = [];
        $this->requireAccount($this->accounts, $command->bankAccountId, $errors);
        $cents = trim($command->amount) === '' ? 0 : AccountAmount::parseCents($command->amount);
        if ($cents === null) {
            $errors['amount'] = 'Bitte einen gültigen Anfangsbestand angeben (z. B. 1.234,56).';
        }
        $date = trim($command->date) === '' ? null : $this->bookingDate($command->date, $errors, 'date');
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->ledger->saveOpeningBalance($command->bankAccountId, (int) $cents, $date);

        return null;
    }
}
