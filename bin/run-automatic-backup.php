#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\Backup\Command\RunAutomaticBackup;
use App\Domain\Backup\BackupRun;
use App\Domain\Backup\BackupRunStatus;
use App\Domain\Backup\BackupRunTrigger;

// Cron script for the automatic backup (Einstellungen → Sicherung →
// "Automatische Sicherung"). Builds the same backup ZIP as the manual
// download (database + storage/uploads/) and uploads it to the selected
// storage targets of the document filing (WebDAV/Nextcloud, FTPS, SFTP, SCP,
// S3, Dropbox, OneDrive, Google Drive, Box, local directory).
//
// It does NOT run inside a web request and has to be scheduled with cron.
// The settings page shows the exact crontab line for the chosen rhythm,
// e.g. daily at 03:15:
//
//   15 3 * * * /usr/bin/php /path/to/app/bin/run-automatic-backup.php >> /path/to/app/storage/logs/automatic-backup.log 2>&1
//
// Every run is logged in backup_runs (visible on the settings page).
// Options:
//   --force  run even if the automatic backup is disabled in the settings.
//
// Exit codes: 0 = success or disabled, 3 = partially failed, 4 = failed.

/** @var \App\Infrastructure\Http\Router $router Unused — we only need the local variables bootstrap.php defines along the way. */
$router = require __DIR__ . '/../src/bootstrap.php';

if (!isset($commandBus) || !isset($backupSettingsRepository)) {
    fwrite(STDERR, "Fehler: \$commandBus/\$backupSettingsRepository wurden nicht aus bootstrap.php übernommen (unerwartete Änderung an bootstrap.php?).\n");
    exit(1);
}

// The whole ZIP is held in memory once for the upload (the storage clients
// take the contents as a string) and building a large archive can take a
// while, so lift both limits for this CLI run.
ini_set('memory_limit', '-1');
set_time_limit(0);

$force = in_array('--force', $argv, true);

echo "Materialverleih — Automatische Sicherung\n";
echo str_repeat('-', 41) . "\n";
echo 'Gestartet: ' . date('Y-m-d H:i:s') . "\n\n";

/** @var \App\Domain\Backup\BackupSettings $settings */
$settings = $backupSettingsRepository->get();

if (!$settings->enabled && !$force) {
    echo "Automatische Sicherung deaktiviert (siehe Einstellungen -> Sicherung).\n";
    exit(0);
}

try {
    $run = $commandBus->dispatch(new RunAutomaticBackup(BackupRunTrigger::Cron));
    assert($run instanceof BackupRun);
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler bei der Sicherung: ' . $e->getMessage() . "\n");
    exit(4);
}

if ($run->fileName !== null) {
    $size = $run->fileSize !== null ? ' (' . number_format($run->fileSize / 1048576, 1, ',', '.') . ' MB)' : '';
    echo "Sicherungsdatei: {$run->fileName}{$size}\n";
}
if ($run->message !== null) {
    fwrite(STDERR, "Fehler: {$run->message}\n");
}
foreach ($run->targetResults as $result) {
    $line = ($result['success'] ? '[OK]     ' : '[FEHLER] ') . "{$result['targetName']}: {$result['remotePath']}";
    if (!$result['success']) {
        $line .= " — {$result['message']}";
        fwrite(STDERR, $line . "\n");
    } else {
        echo $line . "\n";
    }
}

echo "\nErgebnis: {$run->status->label()}\n";
echo 'Fertig: ' . date('Y-m-d H:i:s') . "\n";

exit(match ($run->status) {
    BackupRunStatus::Success => 0,
    BackupRunStatus::Partial => 3,
    BackupRunStatus::Failed => 4,
});
