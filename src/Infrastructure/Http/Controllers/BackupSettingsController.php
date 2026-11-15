<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Backup\Command\ExportBackup;
use App\Application\Backup\Command\ImportBackup;
use App\Application\Backup\Command\RunAutomaticBackup;
use App\Application\Backup\Command\UpdateBackupSettings;
use App\Application\Backup\Query\GetBackupSettings;
use App\Application\Backup\Query\ListBackupRuns;
use App\Application\Command\CommandBus;
use App\Application\DocumentStorage\Query\ListStorageTargets;
use App\Application\Query\QueryBus;
use App\Domain\Backup\BackupRun;
use App\Domain\Backup\BackupRunStatus;
use App\Domain\Backup\BackupRunTrigger;
use App\Domain\Backup\BackupSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Backup\BackupArchiveException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Shared\ProjectPaths;
use RuntimeException;

use function App\Infrastructure\Http\t;

/**
 * Admin-Oberfläche für Export (Download einer vollständigen Backup-ZIP)
 * und Import (Wiederherstellen einer solchen ZIP, ERSETZT dabei alle
 * aktuellen Daten) — siehe App\Infrastructure\Backup\BackupArchiveBuilder
 * bzw. BackupArchiveImporter für die eigentliche Logik. Das Pendant im
 * Web-Installer (public/install.php, Schritt "Wiederherstellen") nutzt
 * dieselben Infrastruktur-Klassen, kann aber mangels bootstrap.php nicht
 * über diesen Controller laufen.
 *
 * Additionally manages the automatic backup to the document storage targets
 * (settings, "run now" and the crontab line for bin/run-automatic-backup.php,
 * see RunAutomaticBackupHandler).
 */
final class BackupSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly string $tempDirectory,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $this->render([], []);
    }

    /** Saves the automatic backup settings (storage targets, path template, schedule). */
    public function saveAutomatic(Request $request): void
    {
        $targetIds = $request->inputArray('backup_storage_targets');

        $old = [
            'backup_enabled' => $request->input('backup_enabled') !== null ? '1' : '',
            'backup_storage_targets' => $targetIds,
            'backup_target_path' => (string) ($request->input('backup_target_path', '') ?? ''),
            'backup_interval' => (string) ($request->input('backup_interval', '') ?? ''),
            'backup_run_time' => (string) ($request->input('backup_run_time', '') ?? ''),
            'backup_weekday' => (string) ($request->input('backup_weekday', '') ?? ''),
            'backup_month_day' => (string) ($request->input('backup_month_day', '') ?? ''),
        ];

        try {
            $this->commands->dispatch(new UpdateBackupSettings(
                enabled: $old['backup_enabled'] === '1',
                storageTargetIds: $targetIds,
                targetPath: $old['backup_target_path'],
                interval: $old['backup_interval'],
                runTime: $old['backup_run_time'],
                weekday: $old['backup_weekday'],
                monthDay: $old['backup_month_day'],
            ));
        } catch (ValidationException $e) {
            $this->render($e->errors(), $old);

            return;
        }

        Flash::success(t('settings.backup.automatic.flash.saved'));
        Response::redirect('/einstellungen/sicherung');
    }

    /** Runs the automatic backup immediately (same as the cron script), e.g. to test the storage targets. */
    public function runNow(Request $request): void
    {
        @set_time_limit(0);

        $run = $this->commands->dispatch(new RunAutomaticBackup(BackupRunTrigger::Manual));
        assert($run instanceof BackupRun);

        $message = sprintf(t('settings.backup.automatic.flash.run_result'), $run->status->label());
        if ($run->message !== null) {
            $message .= ' ' . $run->message;
        }
        foreach ($run->targetResults as $result) {
            if (!$result['success']) {
                $message .= ' ' . $result['targetName'] . ': ' . $result['message'];
            }
        }

        match ($run->status) {
            BackupRunStatus::Success => Flash::success($message),
            default => Flash::error($message),
        };
        Response::redirect('/einstellungen/sicherung');
    }

    /** Erzeugt die Backup-ZIP und liefert sie direkt als Download aus (siehe Response::downloadFile()). */
    public function export(Request $request): void
    {
        try {
            /** @var array{absolutePath: string, downloadName: string} $result */
            $result = $this->commands->dispatch(new ExportBackup());
        } catch (BackupArchiveException | RuntimeException $e) {
            Flash::error(t('settings.backup.flash.export_failed') . ' ' . $e->getMessage());
            Response::redirect('/einstellungen/sicherung');

            return;
        }

        // Die temporäre ZIP wird erst nach dem vollständigen Ausliefern
        // gelöscht (readfile() in Response::downloadFile() beendet den
        // Prozess selbst mit exit() — register_shutdown_function() läuft
        // auch danach noch).
        register_shutdown_function(static function () use ($result): void {
            @unlink($result['absolutePath']);
        });

        Response::downloadFile($result['absolutePath'], $result['downloadName'], 'application/zip');
    }

    /** Nimmt die hochgeladene Backup-ZIP samt Bestätigungstext entgegen und löst das (unwiderrufliche) Wiederherstellen aus. */
    public function import(Request $request): void
    {
        $confirmationText = $request->input('confirmation_text', '') ?? '';
        $uploadedFile = $request->file('backup_zip');

        if ($uploadedFile === null) {
            $this->renderWithError(['backup_zip' => t('settings.backup.error.no_file')], $confirmationText);

            return;
        }

        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $this->renderWithError(['backup_zip' => t('settings.backup.error.upload_failed') . ' (Fehlercode ' . $uploadedFile['error'] . ')'], $confirmationText);

            return;
        }

        $tempDir = $this->tempDirectory;
        if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            $this->renderWithError(['backup_zip' => t('settings.backup.error.temp_dir')], $confirmationText);

            return;
        }

        $stagedZipPath = rtrim($tempDir, '/') . '/upload-' . bin2hex(random_bytes(8)) . '.zip';
        if (!move_uploaded_file($uploadedFile['tmp_name'], $stagedZipPath)) {
            $this->renderWithError(['backup_zip' => t('settings.backup.error.upload_failed')], $confirmationText);

            return;
        }

        try {
            /** @var array{tableCount: int|null, rowCount: int|null, assetFileCount: int|null, migrationsCaughtUp: int, exportedAt: string|null} $summary */
            $summary = $this->commands->dispatch(new ImportBackup($stagedZipPath, $confirmationText));

            Flash::success($this->summaryMessage($summary));
            Response::redirect('/einstellungen/sicherung');
        } catch (ValidationException $e) {
            $this->renderWithError($e->errors(), $confirmationText);
        } catch (BackupArchiveException | RuntimeException $e) {
            $this->renderWithError(['backup_zip' => $e->getMessage()], $confirmationText);
        } finally {
            @unlink($stagedZipPath);
        }
    }

    /** @param array{tableCount: int|null, rowCount: int|null, assetFileCount: int|null, migrationsCaughtUp: int, exportedAt: string|null} $summary */
    private function summaryMessage(array $summary): string
    {
        $message = t('settings.backup.flash.import_succeeded');

        if ($summary['tableCount'] !== null && $summary['rowCount'] !== null) {
            $message .= ' ' . sprintf(
                t('settings.backup.flash.import_summary'),
                $summary['tableCount'],
                $summary['rowCount'],
                $summary['assetFileCount'] ?? 0,
            );
        }

        if ($summary['migrationsCaughtUp'] > 0) {
            $message .= ' ' . sprintf(t('settings.backup.flash.import_migrations_caught_up'), $summary['migrationsCaughtUp']);
        }

        return $message;
    }

    /** @param array<string, string> $errors */
    private function renderWithError(array $errors, string $confirmationText): void
    {
        $this->render($errors, ['confirmation_text' => $confirmationText]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function render(array $errors, array $old): void
    {
        /** @var BackupSettings $settings */
        $settings = $this->queries->ask(new GetBackupSettings());

        echo View::page('settings/backup', [
            'errors' => $errors,
            'old' => $old,
            'settings' => $settings,
            'storageTargets' => $this->queries->ask(new ListStorageTargets()),
            'runs' => $this->queries->ask(new ListBackupRuns(10)),
            'cronLine' => self::crontabLine($settings),
            'serverTime' => date('H:i T'),
        ], activeNav: 'settings-sicherung', title: t('settings.backup.title'));
    }

    /** The complete crontab line for bin/run-automatic-backup.php according to the saved schedule. */
    public static function crontabLine(BackupSettings $settings): string
    {
        $logDirectory = ProjectPaths::rootDirectory() . '/storage/logs';
        // The shell redirect in the cron line fails if the directory is missing.
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }

        return $settings->cronSchedule()
            . ' /usr/bin/php ' . self::shellPath(ProjectPaths::automaticBackupScriptPath())
            . ' >> ' . self::shellPath($logDirectory . '/automatic-backup.log') . ' 2>&1';
    }

    private static function shellPath(string $path): string
    {
        return preg_match('#^[A-Za-z0-9_./-]+$#', $path) === 1 ? $path : escapeshellarg($path);
    }
}
