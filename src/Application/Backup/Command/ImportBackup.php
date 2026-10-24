<?php

declare(strict_types=1);

namespace App\Application\Backup\Command;

use App\Application\Command\CommandInterface;

/**
 * Löst das vollständige Wiederherstellen einer Sicherung aus (siehe
 * BackupArchiveImporter) — ERSETZT dabei unwiderruflich alle aktuellen
 * Datenbank-Inhalte und alle Dateien unterhalb von storage/uploads/.
 * Der Administrator muss dafür $confirmationText exakt gleich
 * CONFIRMATION_PHRASE eintippen (siehe ImportBackupHandler) — die
 * bewusst "starke Sicherheitsabfrage" aus der Produktentscheidung zu
 * dieser Funktion.
 */
final class ImportBackup implements CommandInterface
{
    public const CONFIRMATION_PHRASE = 'DATEN ERSETZEN';

    public function __construct(
        public readonly string $zipAbsolutePath,
        public readonly string $confirmationText,
    ) {
    }
}
