<?php

declare(strict_types=1);

namespace App\Application\Backup\Command;

use App\Application\Command\CommandInterface;

/** Löst den Export einer vollständigen Backup-ZIP aus (Datenbank + Assets, siehe BackupArchiveBuilder). Keine Parameter nötig. */
final class ExportBackup implements CommandInterface
{
}
