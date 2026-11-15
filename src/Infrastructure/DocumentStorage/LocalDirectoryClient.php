<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Writes into a directory on the application server that is synchronised
 * with a cloud service by a separate sync client — used for iCloud Drive,
 * which offers no public file API for servers. base_path is the absolute
 * path of that directory, e.g. the iCloud Drive folder of a Mac
 * (~/Library/Mobile Documents/com~apple~CloudDocs/...) or an rclone mount.
 */
final class LocalDirectoryClient implements StorageClientInterface
{
    public function __construct(private readonly DocumentStorageTarget $target)
    {
    }

    public function upload(string $contents, string $remotePath): void
    {
        $file = $this->absolutePath($remotePath);
        if ($file === $this->root()) {
            throw new StorageUploadException('Es wurde kein Dateiname angegeben.');
        }
        $this->makeDirectory(dirname($file));

        // Write to a temporary file first so the sync client never picks up a half-written document.
        $temporary = dirname($file) . '/.' . basename($file) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $contents) === false) {
            throw new StorageUploadException('Datei konnte nicht geschrieben werden: ' . $this->lastError());
        }
        if (!@rename($temporary, $file)) {
            @unlink($temporary);
            throw new StorageUploadException('Datei konnte nicht gespeichert werden: ' . $this->lastError());
        }
    }

    public function testConnection(): string
    {
        $root = $this->root();
        if (!is_writable($root)) {
            throw new StorageUploadException("Der Ordner \"{$root}\" ist für den Webserver nicht beschreibbar.");
        }

        $testFilename = '.materialverleih-verbindungstest-' . time() . '.txt';
        $this->upload('Materialverleih-Verbindungstest vom ' . date('d.m.Y H:i:s') . "\n", $testFilename);
        if (!@unlink($this->absolutePath($testFilename))) {
            return 'Ordner ist beschreibbar, Testdatei konnte aber nicht automatisch entfernt werden — bitte manuell aufräumen.';
        }

        return 'Ordner ist beschreibbar — Testdatei wurde angelegt und wieder entfernt. Ob die Dateien in der Cloud ankommen, hängt vom Synchronisations-Client auf dem Server ab.';
    }

    public function createDirectory(string $remotePath): void
    {
        $directory = $this->absolutePath($remotePath);
        if ($directory === $this->root()) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }
        $this->makeDirectory($directory);
    }

    public function moveDirectory(string $fromPath, string $toPath): void
    {
        $from = $this->absolutePath($fromPath);
        $to = $this->absolutePath($toPath);
        if ($from === $this->root() || $to === $this->root()) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }
        if (!is_dir($from)) {
            throw new StorageUploadException('Der Ordner "' . trim($fromPath, '/') . '" wurde auf dem Speicherziel nicht gefunden (wurde er dort verschoben oder gelöscht?).');
        }
        if (file_exists($to)) {
            throw new StorageUploadException('Der Ordner "' . trim($toPath, '/') . '" existiert bereits.');
        }

        $this->makeDirectory(dirname($to));
        if (!@rename($from, $to)) {
            throw new StorageUploadException('Ordner konnte nicht umbenannt werden: ' . $this->lastError());
        }
    }

    public function deleteDirectory(string $remotePath): void
    {
        $directory = $this->absolutePath($remotePath);
        if ($directory === $this->root()) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }
        if (!file_exists($directory)) {
            return;
        }
        if (!is_dir($directory) || is_link($directory)) {
            throw new StorageUploadException("\"{$remotePath}\" ist kein Ordner.");
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $ok = $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            if (!$ok) {
                throw new StorageUploadException('Ordner konnte nicht vollständig gelöscht werden: ' . $this->lastError());
            }
        }
        if (!@rmdir($directory)) {
            throw new StorageUploadException('Ordner konnte nicht gelöscht werden: ' . $this->lastError());
        }
    }

    private function root(): string
    {
        $root = rtrim(trim((string) $this->target->basePath), '/\\');
        if ($root === '') {
            throw new StorageUploadException('Es ist kein lokaler Ordner hinterlegt.');
        }
        if (!is_dir($root)) {
            throw new StorageUploadException("Der Ordner \"{$root}\" existiert auf dem Server nicht — ist iCloud Drive dort eingerichtet bzw. eingebunden?");
        }

        return $root;
    }

    /** Absolute path below the root; ".." segments are rejected. */
    private function absolutePath(string $remotePath): string
    {
        $segments = [];
        foreach (preg_split('#[/\\\\]+#', $remotePath) ?: [] as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new StorageUploadException('Pfade mit „..“ sind nicht erlaubt.');
            }
            $segments[] = $segment;
        }

        return $segments === [] ? $this->root() : $this->root() . '/' . implode('/', $segments);
    }

    private function makeDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }
        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new StorageUploadException("Ordner \"{$directory}\" konnte nicht angelegt werden: " . $this->lastError());
        }
    }

    private function lastError(): string
    {
        return error_get_last()['message'] ?? 'unbekannter Fehler';
    }
}
