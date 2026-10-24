<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Backup\DatabaseDumperInterface;
use PDO;
use RuntimeException;

/**
 * Schreibt einen vollständigen SQL-Dump ALLER Tabellen der aktuellen
 * Datenbank in eine Datei (siehe DatabaseDumperInterface für die
 * Begründung des Formats). Bewusst zeilenweise in eine Datei geschrieben
 * (fwrite) statt als ein großer PHP-String im Speicher aufgebaut — bei
 * vielen/großen Tabellen (z. B. Artikelstammdaten über Jahre) bliebe sonst
 * der Speicherverbrauch des PHP-Prozesses unnötig hoch.
 */
final class MariadbDatabaseDumper implements DatabaseDumperInterface
{
    /** Anzahl Datensätze je INSERT-Anweisung (Kompromiss zwischen Dateigröße/Lesbarkeit und Anzahl der Anweisungen). */
    private const ROWS_PER_INSERT = 200;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dumpToFile(string $absoluteOutputPath): array
    {
        $handle = fopen($absoluteOutputPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Dump-Datei \"{$absoluteOutputPath}\" konnte nicht angelegt werden.");
        }

        $tableCount = 0;
        $rowCount = 0;

        try {
            fwrite($handle, "-- Materialverleih Software -- Datenbank-Sicherung\n");
            fwrite($handle, '-- Erstellt am ' . (new \DateTimeImmutable())->format('Y-m-d H:i:s') . "\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

            foreach ($this->tableNames() as $table) {
                $tableCount++;
                $this->dumpTableSchema($handle, $table);
                $rowCount += $this->dumpTableData($handle, $table);
                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            fclose($handle);
        }

        return ['tableCount' => $tableCount, 'rowCount' => $rowCount];
    }

    /** @return string[] */
    private function tableNames(): array
    {
        $stmt = $this->pdo->query('SHOW TABLES');

        return $stmt !== false ? array_column($stmt->fetchAll(PDO::FETCH_NUM), 0) : [];
    }

    /** @param resource $handle */
    private function dumpTableSchema($handle, string $table): void
    {
        $stmt = $this->pdo->query('SHOW CREATE TABLE `' . $table . '`');
        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_NUM) : null;

        if ($row === false || $row === null) {
            throw new RuntimeException("CREATE TABLE für \"{$table}\" konnte nicht ermittelt werden.");
        }

        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($handle, $row[1] . ";\n");
    }

    /** @param resource $handle @return int Anzahl exportierter Zeilen. */
    private function dumpTableData($handle, string $table): int
    {
        $stmt = $this->pdo->query('SELECT * FROM `' . $table . '`');
        if ($stmt === false) {
            return 0;
        }

        $rowCount = 0;
        $columnsLine = null;
        $batch = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rowCount++;

            if ($columnsLine === null) {
                $columnsLine = '(`' . implode('`, `', array_keys($row)) . '`)';
            }

            $batch[] = '(' . implode(', ', array_map(fn ($value) => $this->quoteValue($value), array_values($row))) . ')';

            if (count($batch) >= self::ROWS_PER_INSERT) {
                $this->flushInsertBatch($handle, $table, $columnsLine, $batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->flushInsertBatch($handle, $table, (string) $columnsLine, $batch);
        }

        return $rowCount;
    }

    /** @param resource $handle @param string[] $batch */
    private function flushInsertBatch($handle, string $table, string $columnsLine, array $batch): void
    {
        fwrite($handle, "INSERT INTO `{$table}` {$columnsLine} VALUES\n" . implode(",\n", $batch) . ";\n");
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        // PDO liefert alle Spaltenwerte (auch Zahlen) standardmäßig als
        // String oder null zurück — $pdo->quote() übernimmt das sichere
        // Escaping (inkl. Umschließung mit Anführungszeichen).
        return $this->pdo->quote((string) $value);
    }
}
