<?php

declare(strict_types=1);

namespace App\Application\Backup\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Backup\BackupArchiveImporter;

final class ImportBackupHandler implements CommandHandlerInterface
{
    public function __construct(private readonly BackupArchiveImporter $archiveImporter)
    {
    }

    /** @return array{tableCount: int|null, rowCount: int|null, assetFileCount: int|null, migrationsCaughtUp: int, exportedAt: string|null} */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ImportBackup);

        if (trim($command->confirmationText) !== ImportBackup::CONFIRMATION_PHRASE) {
            throw new ValidationException([
                'confirmation_text' => 'Bitte genau den Text "' . ImportBackup::CONFIRMATION_PHRASE . '" zur Bestätigung eingeben.',
            ]);
        }

        return $this->archiveImporter->import($command->zipAbsolutePath);
    }
}
