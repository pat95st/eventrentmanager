<?php

declare(strict_types=1);

namespace App\Domain\Backup;

/**
 * Erzeugt einen vollständigen SQL-Dump (Schema + Daten) der gesamten
 * Datenbank, als Teil der Backup-Export-Funktion (siehe
 * App\Infrastructure\Backup\BackupArchiveBuilder). Bewusst EIN großer
 * SQL-Text statt eines strukturierten Formats (z. B. JSON je Tabelle):
 * lässt sich beim Import unverändert wie eine normale Migration gegen die
 * Datenbank ausführen (siehe App\Infrastructure\Migrations\MigrationRunner)
 * und bleibt damit exakt kompatibel zu allen bestehenden Tabellen, auch
 * künftigen, ohne dass der Export-Code bei jeder neuen Tabelle angepasst
 * werden müsste.
 */
interface DatabaseDumperInterface
{
    /**
     * @return array{tableCount: int, rowCount: int} Statistik über den erzeugten Dump,
     *         u. a. für die Zusammenfassung im Export-Manifest.
     */
    public function dumpToFile(string $absoluteOutputPath): array;
}
