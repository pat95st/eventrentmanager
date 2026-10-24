#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Infrastructure\Migrations\MigrationFailedException;
use App\Infrastructure\Migrations\MigrationRunner;

// CLI-Werkzeug zum Nachziehen neuer Migrationen nach einem Code-Update, als
// Ersatz für das bisherige manuelle Abarbeiten der einzelnen
// `mysql -u root -p ... < database/migrations/xxx.sql`-Befehle aus der
// README. Nutzt dieselbe DB-Verbindung wie die Anwendung selbst (siehe
// bootstrap.php) und wendet dank App\Infrastructure\Migrations\MigrationRunner
// nur die noch nicht eingespielten Dateien an — beliebig oft wiederholbar.
//
// Aufruf: php bin/migrate.php
/** @var \App\Infrastructure\Http\Router $router Ungenutzt — wir brauchen nur $pdo, das bootstrap.php nebenbei definiert. */
$router = require __DIR__ . '/../src/bootstrap.php';

if (!isset($pdo)) {
    fwrite(STDERR, "Fehler: \$pdo wurde nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

$runner = new MigrationRunner($pdo, __DIR__ . '/../database/migrations');

$pendingCount = count($runner->migrationFiles()) - count($runner->appliedMigrations());
if ($pendingCount <= 0) {
    echo "Keine neuen Migrationen – Datenbank ist bereits aktuell.\n";
    exit(0);
}

echo "{$pendingCount} neue Migration(en) werden eingespielt:\n";

try {
    $log = $runner->applyAll();
} catch (MigrationFailedException $e) {
    foreach ($e->log as $entry) {
        echo sprintf("  [%s] %s\n", $entry['status'] === 'applied' ? ' OK ' : 'FEHLER', $entry['file']);
    }
    fwrite(STDERR, "\n" . $e->getMessage() . "\n");
    exit(1);
}

foreach ($log as $entry) {
    if ($entry['status'] === 'applied') {
        echo "  OK  {$entry['file']}\n";
    }
}

echo "\nFertig.\n";
