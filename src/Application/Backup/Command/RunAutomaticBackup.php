<?php

declare(strict_types=1);

namespace App\Application\Backup\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Backup\BackupRunTrigger;

/**
 * Builds a full backup ZIP and uploads it to the storage targets selected
 * in the backup settings (see RunAutomaticBackupHandler). Dispatched by
 * bin/run-automatic-backup.php (cron) and by the "Jetzt sichern" button.
 */
final class RunAutomaticBackup implements CommandInterface
{
    public function __construct(public readonly BackupRunTrigger $trigger)
    {
    }
}
