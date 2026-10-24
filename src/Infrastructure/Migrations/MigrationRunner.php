<?php

declare(strict_types=1);

namespace App\Infrastructure\Migrations;

use PDO;
use PDOException;

/**
 * Spielt die SQL-Dateien aus database/migrations/ der Reihe nach gegen eine
 * bestehende, leere oder teilweise eingerichtete Datenbank ein und merkt sich
 * in einer eigenen Tabelle (schema_migrations), welche Dateien bereits
 * angewendet wurden — macht den gesamten Lauf idempotent: ein erneuter
 * Aufruf (z. B. nach Beheben eines Fehlers in der Mitte, oder beim
 * nachträglichen Einspielen neuer Migrationen nach einem Update) wendet nur
 * die noch fehlenden Dateien an.
 *
 * Bewusst KEIN Rückgriff auf database/schema.sql als Abkürzung für neue
 * Installationen: schema.sql wird im Projekt nicht zuverlässig synchron zu
 * database/migrations/ gepflegt, siehe README. Die Migrationsdateien selbst
 * sind die einzige verlässliche, vollständige Quelle des aktuellen Schemas.
 *
 * Genutzt vom Web-Installer (public/install.php) für frische Installationen
 * und von bin/migrate.php für das Nachziehen neuer Migrationen bei
 * bestehenden Installationen nach einem Update.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsDir,
    ) {
    }

    /**
     * @return array<int, array{file: string, status: string}> Jede im Dateisystem
     *         gefundene Migration mit ihrem Status: 'applied' (diesmal angewendet),
     *         'already_applied' (lief bereits bei einem früheren Aufruf) oder
     *         'failed' (Abbruch — alle danach folgenden Dateien werden gar nicht
     *         erst versucht, siehe Schleifenabbruch unten).
     * @throws MigrationFailedException Wirft nach dem Eintragen der fehlgeschlagenen
     *         Datei in das Log, mit der kompletten Fehlermeldung des PDO-Treibers.
     */
    public function applyAll(): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->appliedMigrations();
        $log = [];

        foreach ($this->migrationFiles() as $file) {
            $basename = basename($file);

            if (in_array($basename, $applied, true)) {
                $log[] = ['file' => $basename, 'status' => 'already_applied'];

                continue;
            }

            try {
                $this->applyOne($file, $basename);
                $log[] = ['file' => $basename, 'status' => 'applied'];
            } catch (PDOException $e) {
                $log[] = ['file' => $basename, 'status' => 'failed'];

                throw new MigrationFailedException($basename, $e->getMessage(), $log, previous: $e);
            }
        }

        return $log;
    }

    /** @return string[] Dateinamen (ohne Pfad) aller im Dateisystem vorhandenen Migrationen, aufsteigend sortiert. */
    public function migrationFiles(): array
    {
        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    /** @return string[] Dateinamen, die laut schema_migrations bereits angewendet wurden. */
    public function appliedMigrations(): array
    {
        $this->ensureMigrationsTable();

        $stmt = $this->pdo->query('SELECT migration FROM schema_migrations');

        return $stmt !== false ? array_column($stmt->fetchAll(), 'migration') : [];
    }

    private function applyOne(string $file, string $basename): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new PDOException("Datei \"{$file}\" konnte nicht gelesen werden.");
        }

        // "USE <datenbank>;"-Zeilen (in mehreren Migrationsdateien aus der
        // Historie vorhanden, als die Datenbank noch fest "materialverleih"
        // hieß) entfernen: die PDO-Verbindung ist bereits über den DSN an die
        // vom Administrator gewählte Datenbank gebunden, ein hartcodierter
        // Datenbankname in der Migration selbst würde bei abweichendem Namen
        // fehlschlagen oder (schlimmer) stillschweigend in die falsche
        // Datenbank schreiben.
        $sql = (string) preg_replace('/^\s*USE\s+`?\w+`?\s*;\s*$/mi', '', $sql);

        // PDO_MySQL führt bei exec() mehrere, durch Semikolon getrennte
        // Anweisungen in einem Aufruf aus (nativer mysqlnd-Treiber,
        // PDO::MYSQL_ATTR_MULTI_STATEMENTS ist standardmäßig aktiv) — kein
        // eigener, fehleranfälliger SQL-Statement-Splitter nötig.
        $this->pdo->exec($sql);

        $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, :applied_at)');
        $stmt->execute([
            'migration' => $basename,
            'applied_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function ensureMigrationsTable(): void
    {
        // Bewusst ohne "ENGINE = InnoDB DEFAULT CHARSET = ..."-Zusatz (anders
        // als die Tabellen in den Migrationsdateien selbst): die Zieldatenbank
        // wurde laut Installationsanleitung bereits mit utf8mb4 als
        // Standard-Zeichensatz angelegt, neue Tabellen erben diesen ohne
        // explizite Angabe. Das hält diese eine, vom MigrationRunner selbst
        // verwaltete Tabelle als einzige portabel genug, um auch gegen eine
        // andere PDO-Treiber-Testdatenbank (siehe Tests) ausführbar zu sein.
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration  VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME     NOT NULL
            )'
        );
    }
}
