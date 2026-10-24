<?php

declare(strict_types=1);

namespace App\Domain\Backup;

/**
 * Spielt einen von DatabaseDumperInterface erzeugten SQL-Dump wieder ein —
 * ERSETZT dabei vollständig den aktuellen Inhalt jeder im Dump enthaltenen
 * Tabelle (DROP TABLE + CREATE TABLE + INSERT, siehe
 * MariadbDatabaseRestorer), analog zu einem klassischen
 * "mysql < backup.sql"-Restore. Bewusst kein Zusammenführen/Merge mit
 * bestehenden Daten — das wäre bei Fremdschlüsseln/eindeutigen Indizes
 * ohnehin nicht widerspruchsfrei möglich und würde beim Nutzer falsche
 * Erwartungen wecken ("nur fehlende Datensätze ergänzen").
 *
 * Nach dem Restore WIRD BEWUSST NICHT automatisch migriert — das macht
 * App\Infrastructure\Backup\BackupArchiveImporter im Anschluss separat,
 * damit auch ein Backup aus einer älteren Anwendungsversion (mit seitdem
 * neu hinzugekommenen Migrationen) anschließend auf den aktuellen
 * Schema-Stand gebracht wird.
 */
interface DatabaseRestorerInterface
{
    public function restoreFromFile(string $absoluteSqlPath): void;
}
