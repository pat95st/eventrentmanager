<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\BankAccountRepositoryInterface;
use DateTimeImmutable;

final class UpdateBankAccountHandler implements CommandHandlerInterface
{
    public function __construct(private readonly BankAccountRepositoryInterface $bankAccounts)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateBankAccount);

        $account = $this->bankAccounts->findById($command->id);
        if ($account === null) {
            throw new NotFoundException("Bankverbindung \"{$command->id}\" wurde nicht gefunden.");
        }

        [$label, $accountHolder, $bankName, $iban, $bic, $errors] = ValidatesBankAccountInput::validate(
            $command->label, $command->accountHolder, $command->bankName, $command->iban, $command->bic
        );
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $account->label = $label;
        $account->accountHolder = $accountHolder;
        $account->bankName = $bankName;
        $account->iban = $iban;
        $account->bic = $bic;
        $account->updatedAt = new DateTimeImmutable();
        // Die Standard-Markierung kann nur gesetzt, nicht per Abwählen
        // entfernt werden — es muss immer ein Standardkonto geben; gewechselt
        // wird, indem ein anderes Konto als Standard markiert wird.
        if ($command->isDefault) {
            $account->isDefault = true;
        }
        $this->bankAccounts->save($account);
        if ($command->isDefault) {
            $this->bankAccounts->markAsDefault($account->id);
        }

        return null;
    }
}
