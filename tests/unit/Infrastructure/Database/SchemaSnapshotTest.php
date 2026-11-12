<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Database;

use Codeception\Test\Unit;

/**
 * Sichert database/schema.sql als vollständigen Schema-Snapshot ab — ohne
 * Datenbank, rein über den Dateiinhalt.
 *
 * schema.sql wird von public/install.php für Neuinstallationen und von der
 * Acceptance-Suite (tests/acceptance.suite.yml) als Dump eingespielt, die
 * Migrationen in database/migrations/ laufen dort NICHT. Fehlt in schema.sql
 * eine Tabelle aus einer neuen Migration, steht eine Neuinstallation bzw. die
 * Test-Datenbank ohne sie da (so geschehen u. a. mit trusted_devices,
 * dry_hire_tiers, company_holidays).
 */
final class SchemaSnapshotTest extends Unit
{
    private const SCHEMA_FILE = __DIR__ . '/../../../../database/schema.sql';
    private const MIGRATIONS_DIR = __DIR__ . '/../../../../database/migrations';

    public function testSchemaDoesNotSwitchToAHardcodedDatabase(): void
    {
        $sql = $this->withoutComments((string) file_get_contents(self::SCHEMA_FILE));

        // Sonst würde der Dump statt in die Test-/Ziel-Datenbank in die
        // Produktivdatenbank "materialverleih" geschrieben.
        $this->assertDoesNotMatchRegularExpression('/\bCREATE\s+DATABASE\b/i', $sql);
        $this->assertDoesNotMatchRegularExpression('/^\s*USE\s+/mi', $sql);
    }

    public function testSchemaContainsEveryTableTheMigrationsLeaveBehind(): void
    {
        $expected = $this->tablesAfterAllMigrations();
        $actual = $this->createdTables((string) file_get_contents(self::SCHEMA_FILE));

        $this->assertSame([], array_values(array_diff($expected, $actual)), 'Tabellen aus database/migrations/ fehlen in database/schema.sql');
    }

    public function testSchemaDoesNotCreateTablesThatMigrationsDropped(): void
    {
        $dropped = [];
        foreach ($this->migrationFiles() as $file) {
            $dropped = [...$dropped, ...$this->droppedTables((string) file_get_contents($file))];
        }
        $stillDropped = array_diff($dropped, $this->tablesAfterAllMigrations());
        $actual = $this->createdTables((string) file_get_contents(self::SCHEMA_FILE));

        $this->assertSame([], array_values(array_intersect($stillDropped, $actual)), 'database/schema.sql legt Tabellen an, die eine Migration gelöscht hat');
    }

    /**
     * @return list<string>
     */
    private function tablesAfterAllMigrations(): array
    {
        $tables = [];
        foreach ($this->migrationFiles() as $file) {
            $sql = (string) file_get_contents($file);
            foreach ($this->createdTables($sql) as $table) {
                $tables[$table] = true;
            }
            foreach ($this->droppedTables($sql) as $table) {
                unset($tables[$table]);
            }
        }
        $names = array_keys($tables);
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $files = glob(self::MIGRATIONS_DIR . '/*.sql') ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function createdTables(string $sql): array
    {
        preg_match_all('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $this->withoutComments($sql), $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /**
     * @return list<string>
     */
    private function droppedTables(string $sql): array
    {
        preg_match_all('/\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?(\w+)`?/i', $this->withoutComments($sql), $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    private function withoutComments(string $sql): string
    {
        $sql = (string) preg_replace('#/\*.*?\*/#s', '', $sql);

        return (string) preg_replace('/--[^\n]*/', '', $sql);
    }
}
