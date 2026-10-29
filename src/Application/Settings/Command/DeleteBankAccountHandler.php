<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Settings\BankAccountRepositoryInterface;

/**
 * Löscht eine Bankverbindung. Rechnungen, die genau dieses Konto gewählt
 * hatten, fallen per FK (ON DELETE SET NULL) auf das Standardkonto zurück.
 * Wird das Standardkonto gelöscht, übernimmt das nächste verbleibende Konto
 * die Standard-Markierung.
 */
final class DeleteBankAccountHandler implements CommandHandlerInterface
{
    public function __construct(private readonly BankAccountRepositoryInterface $bankAccounts)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteBankAccount);

        $account = $this->bankAccounts->findById($command->id);
        if ($account === null) {
            throw new NotFoundException("Bankverbindung \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->bankAccounts->delete($command->id);

        if ($account->isDefault) {
            $next = $this->bankAccounts->findAll()[0] ?? null;
            if ($next !== null) {
                $this->bankAccounts->markAsDefault($next->id);
            }
        }

        return null;
    }
}
