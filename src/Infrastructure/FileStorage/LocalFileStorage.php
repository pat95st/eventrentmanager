<?php

declare(strict_types=1);

namespace App\Infrastructure\FileStorage;

use RuntimeException;

/**
 * Speichert hochgeladene Dateien lokal unterhalb von storage/uploads/.
 * Für Rechnungen/E-Mail-PDFs ist damit bewusst kein Zugriff über public/
 * möglich — der Download läuft immer über den RmaController, der dabei
 * auch prüfen kann, ob der Zugriff erlaubt ist.
 */
final class LocalFileStorage
{
    public function __construct(private readonly string $baseDirectory)
    {
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $uploadedFile Aus $_FILES
     * @return array{storedFilename: string, mimeType: string, sizeBytes: int, originalFilename: string}
     */
    public function storeUploadedFile(array $uploadedFile, string $subDirectory, string $storedFilenameWithoutExtension): array
    {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Datei-Upload fehlgeschlagen (Fehlercode ' . $uploadedFile['error'] . ').');
        }

        $targetDir = rtrim($this->baseDirectory, '/') . '/' . trim($subDirectory, '/');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Verzeichnis \"{$targetDir}\" konnte nicht angelegt werden.");
        }

        $originalFilename = basename($uploadedFile['name']);
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $storedFilename = $storedFilenameWithoutExtension . ($extension !== '' ? '.' . strtolower($extension) : '');

        $targetPath = $targetDir . '/' . $storedFilename;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        return [
            'storedFilename' => $storedFilename,
            'mimeType' => $uploadedFile['type'] !== '' ? $uploadedFile['type'] : 'application/octet-stream',
            'sizeBytes' => $uploadedFile['size'],
            'originalFilename' => $originalFilename,
        ];
    }

    /**
     * Speichert rohe Binärdaten (z. B. einen aus einer E-Mail extrahierten
     * Anhang, siehe App\Infrastructure\Mail\MimeMessageParser) als neue
     * Datei — anders als storeUploadedFile() KEIN move_uploaded_file()
     * (das nur mit echten HTTP-Uploads aus $_FILES funktioniert), sondern
     * ein einfaches file_put_contents(). Für den Beleg-Posteingang-Import
     * die sauberere Variante gegenüber "erst in eine Temp-Datei schreiben,
     * dann storeLocalFile() aufrufen", da die Rohbytes hier ohnehin schon
     * vollständig im Speicher vorliegen.
     *
     * @return array{storedFilename: string, mimeType: string, sizeBytes: int, originalFilename: string}
     */
    public function storeRawBytes(string $bytes, string $subDirectory, string $storedFilenameWithoutExtension, string $originalFilename, string $mimeType): array
    {
        $targetDir = rtrim($this->baseDirectory, '/') . '/' . trim($subDirectory, '/');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Verzeichnis \"{$targetDir}\" konnte nicht angelegt werden.");
        }

        $originalFilename = basename($originalFilename);
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $storedFilename = $storedFilenameWithoutExtension . ($extension !== '' ? '.' . strtolower($extension) : '');

        $targetPath = $targetDir . '/' . $storedFilename;

        if (file_put_contents($targetPath, $bytes) === false) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        return [
            'storedFilename' => $storedFilename,
            'mimeType' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
            'sizeBytes' => strlen($bytes),
            'originalFilename' => $originalFilename,
        ];
    }

    /**
     * Kopiert eine bereits auf der Platte liegende Datei (KEIN
     * $_FILES-Upload) in ein Zielverzeichnis unterhalb von storage/uploads/
     * — z. B. um einen Beleg-Posteingang-Anhang beim "Zu Beleg umwandeln"
     * (siehe ConvertReceiptInboxAttachmentToReceiptHandler) von
     * storage/uploads/receipt-inbox/ nach storage/uploads/receipts/ zu
     * übernehmen. $deleteSource = true löscht die Quelldatei nach
     * erfolgreichem Kopieren (für den Fall, dass die Quelle eine
     * Temp-Datei war, die sonst niemand mehr aufräumt); beim Umwandeln
     * eines Posteingang-Anhangs wird bewusst false übergeben, damit die
     * Inbox-Kopie erhalten bleibt (falls das Formular später nochmal
     * fehlschlägt, soll der Anhang nicht verloren gehen).
     *
     * @return array{storedFilename: string, mimeType: string, sizeBytes: int, originalFilename: string}
     */
    public function storeLocalFile(string $sourceAbsolutePath, string $subDirectory, string $storedFilenameWithoutExtension, string $originalFilename, bool $deleteSource = true): array
    {
        if (!is_file($sourceAbsolutePath)) {
            throw new RuntimeException("Quelldatei \"{$sourceAbsolutePath}\" wurde nicht gefunden.");
        }

        $targetDir = rtrim($this->baseDirectory, '/') . '/' . trim($subDirectory, '/');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Verzeichnis \"{$targetDir}\" konnte nicht angelegt werden.");
        }

        $originalFilename = basename($originalFilename);
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $storedFilename = $storedFilenameWithoutExtension . ($extension !== '' ? '.' . strtolower($extension) : '');

        $targetPath = $targetDir . '/' . $storedFilename;

        if (!copy($sourceAbsolutePath, $targetPath)) {
            throw new RuntimeException('Datei konnte nicht kopiert werden.');
        }

        $sizeBytes = filesize($targetPath);
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($targetPath) ?: 'application/octet-stream') : 'application/octet-stream';

        if ($deleteSource) {
            unlink($sourceAbsolutePath);
        }

        return [
            'storedFilename' => $storedFilename,
            'mimeType' => $mimeType,
            'sizeBytes' => $sizeBytes !== false ? $sizeBytes : 0,
            'originalFilename' => $originalFilename,
        ];
    }

    public function absolutePath(string $subDirectory, string $storedFilename): string
    {
        return rtrim($this->baseDirectory, '/') . '/' . trim($subDirectory, '/') . '/' . $storedFilename;
    }

    public function delete(string $subDirectory, string $storedFilename): void
    {
        $path = $this->absolutePath($subDirectory, $storedFilename);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
