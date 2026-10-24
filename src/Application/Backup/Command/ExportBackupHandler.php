<?php

declare(strict_types=1);

namespace App\Application\Backup\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Infrastructure\Backup\BackupArchiveBuilder;

final class ExportBackupHandler implements CommandHandlerInterface
{
    public function __construct(private readonly BackupArchiveBuilder $archiveBuilder)
    {
    }

    /** @return array{absolutePath: string, downloadName: string} */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ExportBackup);

        return $this->archiveBuilder->build();
    }
}
