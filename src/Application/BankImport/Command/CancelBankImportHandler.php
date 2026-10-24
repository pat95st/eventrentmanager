<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Infrastructure\BankImport\BankImportStaging;

/** Verwirft die Zwischenablage ohne weitere Prüfung (idempotent — unbekannte/bereits verworfene importId ist kein Fehler). */
final class CancelBankImportHandler implements CommandHandlerInterface
{
    public function __construct(private readonly BankImportStaging $staging)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CancelBankImport);

        $this->staging->discard($command->importId);

        return null;
    }
}
