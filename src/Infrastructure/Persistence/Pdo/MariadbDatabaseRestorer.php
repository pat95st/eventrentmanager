<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Backup\DatabaseRestorerInterface;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Spielt einen von MariadbDatabaseDumper erzeugten SQL-Dump wieder ein
 * (siehe DatabaseRestorerInterface für die Begründung des "komplett
 * ersetzen"-Verhaltens). Technisch analog zu
 * App\Infrastructure\Migrations\MigrationRunner::applyOne() — liest die
 * komplette Datei ein und führt sie über PDO::exec() als eine
 * Mehrfach-Anweisung aus (PDO_MySQL unterstützt das nativ).
 */
final class MariadbDatabaseRestorer implements DatabaseRestorerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function restoreFromFile(string $absoluteSqlPath): void
    {
        if (!is_file($absoluteSqlPath)) {
            throw new RuntimeException("Dump-Datei \"{$absoluteSqlPath}\" wurde nicht gefunden.");
        }

        $sql = file_get_contents($absoluteSqlPath);
        if ($sql === false) {
            throw new RuntimeException("Dump-Datei \"{$absoluteSqlPath}\" konnte nicht gelesen werden.");
        }

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Die Sicherung konnte nicht eingespielt werden: ' . $e->getMessage()
                . ' (eventuell beschädigte oder zu einer anderen Anwendungsversion gehörende Sicherungsdatei).',
                previous: $e,
            );
        }
    }
}
