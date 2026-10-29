<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\BankAccount;
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateBankAccountHandler implements CommandHandlerInterface
{
    public function __construct(private readonly BankAccountRepositoryInterface $bankAccounts)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateBankAccount);

        [$label, $accountHolder, $bankName, $iban, $bic, $errors] = ValidatesBankAccountInput::validate(
            $command->label, $command->accountHolder, $command->bankName, $command->iban, $command->bic
        );
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // Das erste angelegte Konto wird automatisch Standardkonto.
        $isDefault = $command->isDefault || $this->bankAccounts->findDefault() === null;

        $now = new DateTimeImmutable();
        $account = new BankAccount(
            id: Uuid::v4(),
            label: $label,
            accountHolder: $accountHolder,
            bankName: $bankName,
            iban: $iban,
            bic: $bic,
            isDefault: $isDefault,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->bankAccounts->save($account);
        if ($isDefault) {
            $this->bankAccounts->markAsDefault($account->id);
        }

        return $account->id;
    }
}
