<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Backup\Command\ExportBackup;
use App\Application\Backup\Command\ImportBackup;
use App\Application\Command\CommandBus;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Backup\BackupArchiveException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
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
 */
final class BackupSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly string $tempDirectory,
    ) {
    }

    public function index(Request $request): void
    {
        echo View::page('settings/backup', [
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-sicherung', title: t('settings.backup.title'));
    }

    /** Erzeugt die Backup-ZIP und liefert sie direkt als Download aus (siehe Response::downloadFile()). */
    public function export(Request $request): void
    {
        try {
            /** @var array{absolutePath: string, downloadName: string} $result */
            $result = $this->commands->dispatch(new ExportBackup());
        } catch (BackupArchiveException|RuntimeException $e) {
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
        } catch (BackupArchiveException|RuntimeException $e) {
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
        echo View::page('settings/backup', [
            'errors' => $errors,
            'old' => ['confirmation_text' => $confirmationText],
        ], activeNav: 'settings-sicherung', title: t('settings.backup.title'));
    }
}
