<?php

declare(strict_types=1);

namespace App\Infrastructure\Backup;

use App\Domain\Backup\DatabaseDumperInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Baut die vollständige Backup-ZIP für den Export: einen SQL-Dump der
 * gesamten Datenbank (database.sql, siehe DatabaseDumperInterface), eine
 * 1:1-Kopie aller Dateien unterhalb von storage/uploads/ (unter assets/
 * im Archiv) sowie ein kleines manifest.json mit Metadaten zur Anzeige
 * beim späteren Import (siehe BackupArchiveImporter::readManifest()).
 *
 * BEWUSST NICHT im Archiv enthalten: storage/database.yaml (die
 * DB-Zugangsdaten der QUELL-Installation) — ein Restore erfolgt immer auf
 * eine bereits verbundene Datenbank (Installationsassistent oder
 * bestehende Installation), die Zugangsdaten des Ziels dürfen dabei nicht
 * überschrieben werden.
 */
final class BackupArchiveBuilder
{
    public function __construct(
        private readonly DatabaseDumperInterface $dumper,
        private readonly string $uploadsDirectory,
        private readonly string $tempDirectory,
    ) {
    }

    /** @return array{absolutePath: string, downloadName: string} */
    public function build(): array
    {
        if (!is_dir($this->tempDirectory) && !mkdir($this->tempDirectory, 0775, true) && !is_dir($this->tempDirectory)) {
            throw new BackupArchiveException("Temporäres Verzeichnis \"{$this->tempDirectory}\" konnte nicht angelegt werden.");
        }

        $downloadName = 'materialverleih-sicherung-' . (new \DateTimeImmutable())->format('Y-m-d_H-i-s') . '.zip';
        $zipPath = rtrim($this->tempDirectory, '/') . '/' . bin2hex(random_bytes(8)) . '.zip';
        $sqlDumpPath = rtrim($this->tempDirectory, '/') . '/' . bin2hex(random_bytes(8)) . '.sql';

        try {
            $dumpStats = $this->dumper->dumpToFile($sqlDumpPath);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new BackupArchiveException('Die Sicherungs-ZIP konnte nicht angelegt werden.');
            }

            $zip->addFile($sqlDumpPath, 'database.sql');

            $assetFileCount = $this->addAssetsToZip($zip);

            $manifest = [
                'exported_at' => (new \DateTimeImmutable())->format('c'),
                'table_count' => $dumpStats['tableCount'],
                'row_count' => $dumpStats['rowCount'],
                'asset_file_count' => $assetFileCount,
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            $zip->close();
        } finally {
            @unlink($sqlDumpPath);
        }

        return ['absolutePath' => $zipPath, 'downloadName' => $downloadName];
    }

    /** Fügt jede Datei unterhalb von storage/uploads/ unter assets/ ins Archiv ein. @return int Anzahl hinzugefügter Dateien. */
    private function addAssetsToZip(ZipArchive $zip): int
    {
        if (!is_dir($this->uploadsDirectory)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->uploadsDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $relativePath = substr($fileInfo->getPathname(), strlen(rtrim($this->uploadsDirectory, '/')) + 1);
            $zip->addFile($fileInfo->getPathname(), 'assets/' . $relativePath);
            $count++;
        }

        return $count;
    }
}
