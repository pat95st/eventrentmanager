<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Migrations;

use Codeception\Test\Unit;

/**
 * Wächter gegen ein veraltetes database/schema.sql.
 *
 * schema.sql vermerkt am Ende in schema_migrations, welche Migrationen es
 * bereits enthält. Der Web-Installer zieht fehlende Migrationen zwar selbst
 * nach (siehe applyFreshSchema() in public/install.php), die Acceptance-Suite
 * lädt aber ausschließlich schema.sql (siehe tests/acceptance.suite.yml) und
 * liefe sonst gegen ein unvollständiges Schema. Wer eine Migration hinzufügt,
 * muss sie deshalb auch in den Snapshot übernehmen.
 */
final class SchemaSnapshotTest extends Unit
{
    private const ROOT = __DIR__ . '/../../../..';

    /** @return list<string> */
    private function migrationFiles(): array
    {
        $files = array_map('basename', glob(self::ROOT . '/database/migrations/*.sql') ?: []);
        sort($files, SORT_STRING);

        return $files;
    }

    /** @return list<string> */
    private function migrationsRecordedInSnapshot(): array
    {
        $sql = (string) file_get_contents(self::ROOT . '/database/schema.sql');
        $pos = strrpos($sql, 'INSERT INTO schema_migrations');
        self::assertNotFalse($pos, 'schema.sql muss seinen Migrationsstand in schema_migrations vermerken.');

        preg_match_all("/\\('([^']+\\.sql)',\\s*NOW\\(\\)\\)/", substr($sql, $pos), $matches);
        $recorded = $matches[1];
        sort($recorded, SORT_STRING);

        return $recorded;
    }

    public function testSnapshotRecordsEveryMigrationFile(): void
    {
        $missing = array_values(array_diff($this->migrationFiles(), $this->migrationsRecordedInSnapshot()));

        self::assertSame(
            [],
            $missing,
            'database/schema.sql ist veraltet: Inhalt dieser Migrationen in den Snapshot übernehmen '
            . 'und sie dort in schema_migrations eintragen.'
        );
    }

    public function testSnapshotRecordsOnlyExistingMigrationFiles(): void
    {
        $unknown = array_values(array_diff($this->migrationsRecordedInSnapshot(), $this->migrationFiles()));

        self::assertSame([], $unknown, 'schema.sql vermerkt Migrationen, die es in database/migrations/ nicht gibt.');
    }

    public function testSnapshotRecordsNoMigrationTwice(): void
    {
        $recorded = $this->migrationsRecordedInSnapshot();

        self::assertSame(array_values(array_unique($recorded)), $recorded);
    }

    /**
     * Migrationen wählen keine Datenbank aus: MigrationRunner, Web-Installer
     * und "mysql <datenbank> < datei.sql" sind bereits mit der richtigen
     * Datenbank verbunden. Ein fest verdrahtetes "USE materialverleih" würde
     * bei abweichendem Datenbanknamen fehlschlagen oder in die falsche
     * Datenbank schreiben.
     */
    public function testMigrationsDoNotSelectADatabase(): void
    {
        $offending = [];
        foreach (glob(self::ROOT . '/database/migrations/*.sql') ?: [] as $file) {
            if (preg_match('/^\s*USE\s+`?\w+`?\s*;/mi', (string) file_get_contents($file)) === 1) {
                $offending[] = basename($file);
            }
        }

        self::assertSame([], $offending, 'Migrationen dürfen kein "USE <datenbank>;" enthalten.');
    }
}
