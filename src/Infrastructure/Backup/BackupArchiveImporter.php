<?php

declare(strict_types=1);

namespace App\Infrastructure\Backup;

use App\Domain\Backup\DatabaseRestorerInterface;
use App\Infrastructure\Migrations\MigrationFailedException;
use App\Infrastructure\Migrations\MigrationRunner;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Spielt eine von BackupArchiveBuilder erzeugte Sicherungs-ZIP wieder ein:
 *
 *  1. ZIP in ein temporäres Verzeichnis entpacken.
 *  2. database.sql über DatabaseRestorerInterface einspielen — ERSETZT
 *     dabei vollständig den Inhalt jeder im Dump enthaltenen Tabelle
 *     (siehe DatabaseRestorerInterface).
 *  3. Alle noch fehlenden Migrationen nachziehen (MigrationRunner), damit
 *     auch eine Sicherung aus einer älteren Anwendungsversion anschließend
 *     auf dem aktuellen Schema-Stand ist.
 *  4. storage/uploads/ komplett durch den Inhalt von assets/ aus dem
 *     Archiv ersetzen (ebenfalls vollständiges Ersetzen, keine Fusion).
 *
 * Verwendet sowohl vom Web-Installer (public/install.php, "aus Sicherung
 * wiederherstellen") als auch vom admin-seitigen Import
 * (BackupSettingsController) — beide erzeugen vorher eine passend
 * verbundene PDO-Instanz und übergeben sie demselben Importer.
 */
final class BackupArchiveImporter
{
    public function __construct(
        private readonly DatabaseRestorerInterface $restorer,
        private readonly MigrationRunner $migrationRunner,
        private readonly string $uploadsDirectory,
        private readonly string $tempDirectory,
    ) {
    }

    /** @return array{tableCount: int|null, rowCount: int|null, assetFileCount: int|null, migrationsCaughtUp: int, exportedAt: string|null} */
    public function import(string $zipAbsolutePath): array
    {
        if (!is_file($zipAbsolutePath)) {
            throw new BackupArchiveException('Die Sicherungsdatei wurde nicht gefunden.');
        }

        if (!is_dir($this->tempDirectory) && !mkdir($this->tempDirectory, 0775, true) && !is_dir($this->tempDirectory)) {
            throw new BackupArchiveException("Temporäres Verzeichnis \"{$this->tempDirectory}\" konnte nicht angelegt werden.");
        }

        $extractDir = rtrim($this->tempDirectory, '/') . '/restore-' . bin2hex(random_bytes(8));

        try {
            $this->extractZip($zipAbsolutePath, $extractDir);

            $sqlPath = $extractDir . '/database.sql';
            if (!is_file($sqlPath)) {
                throw new BackupArchiveException('Die ZIP-Datei enthält keine "database.sql" und ist daher keine gültige Sicherung dieser Anwendung.');
            }

            $manifest = $this->readManifestFrom($extractDir);

            $this->restorer->restoreFromFile($sqlPath);

            try {
                $migrationLog = $this->migrationRunner->applyAll();
            } catch (MigrationFailedException $e) {
                throw new BackupArchiveException(
                    'Die Datenbank wurde aus der Sicherung wiederhergestellt, aber beim anschließenden Nachziehen '
                    . 'neuerer Migrationen trat ein Fehler auf: ' . $e->getMessage()
                    . ' Bitte das Problem beheben und die Migrationen danach manuell nachziehen (bin/migrate.php).',
                    previous: $e,
                );
            }
            $migrationsCaughtUp = count(array_filter($migrationLog, static fn (array $entry) => $entry['status'] === 'applied'));

            $assetsDir = $extractDir . '/assets';
            if (is_dir($assetsDir)) {
                $this->replaceUploadsWith($assetsDir);
            }

            return [
                'tableCount' => $manifest['table_count'] ?? null,
                'rowCount' => $manifest['row_count'] ?? null,
                'assetFileCount' => $manifest['asset_file_count'] ?? null,
                'migrationsCaughtUp' => $migrationsCaughtUp,
                'exportedAt' => $manifest['exported_at'] ?? null,
            ];
        } finally {
            $this->deleteRecursive($extractDir);
        }
    }

    private function extractZip(string $zipAbsolutePath, string $extractDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath) !== true) {
            throw new BackupArchiveException('Die Datei ist keine gültige ZIP-Datei oder ist beschädigt.');
        }

        if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            $zip->close();

            throw new BackupArchiveException("Temporäres Verzeichnis \"{$extractDir}\" konnte nicht angelegt werden.");
        }

        $zip->extractTo($extractDir);
        $zip->close();
    }

    /** @return array<string, mixed> */
    private function readManifestFrom(string $extractDir): array
    {
        $manifestPath = $extractDir . '/manifest.json';
        if (!is_file($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Löscht den kompletten Inhalt von storage/uploads/ und ersetzt ihn durch den Inhalt von $assetsDir. */
    private function replaceUploadsWith(string $assetsDir): void
    {
        if (!is_dir($this->uploadsDirectory) && !mkdir($this->uploadsDirectory, 0775, true) && !is_dir($this->uploadsDirectory)) {
            throw new BackupArchiveException("Verzeichnis \"{$this->uploadsDirectory}\" konnte nicht angelegt werden.");
        }

        $this->deleteContentsOf($this->uploadsDirectory);
        $this->copyRecursive($assetsDir, $this->uploadsDirectory);
    }

    private function deleteContentsOf(string $directory): void
    {
        $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            /** @var SplFileInfo $entry */
            if ($entry->isDir()) {
                $this->deleteRecursive($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
    }

    private function copyRecursive(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            $relativePath = substr($fileInfo->getPathname(), strlen(rtrim($source, '/')) + 1);
            $targetPath = rtrim($destination, '/') . '/' . $relativePath;

            if ($fileInfo->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0775, true);
                }

                continue;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                throw new RuntimeException("Verzeichnis \"{$targetDir}\" konnte nicht angelegt werden.");
            }

            if (!copy($fileInfo->getPathname(), $targetPath)) {
                throw new RuntimeException("Datei \"{$relativePath}\" konnte nicht wiederhergestellt werden.");
            }
        }
    }

    private function deleteRecursive(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($path);
    }
}
