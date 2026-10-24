<?php

declare(strict_types=1);

namespace App\Infrastructure\Backup;

use RuntimeException;

/** Nutzer-verständliche Fehlermeldung rund um den Export/Import einer Sicherungs-ZIP (siehe BackupArchiveBuilder/BackupArchiveImporter). */
final class BackupArchiveException extends RuntimeException
{
}
