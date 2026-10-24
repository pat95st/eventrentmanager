<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;

/**
 * FTPS-Client über curl (ext-ftp ist auf vielen Hosting-Umgebungen nicht
 * verfügbar, curl unterstützt FTP/FTPS jedoch nativ und robuster). Nutzt
 * CURLOPT_UPLOAD mit einer temporären Datei als Quelle, da curl beim
 * FTP-Upload einen Stream/Filehandle erwartet statt eines reinen
 * In-Memory-Strings (siehe CURLOPT_INFILE).
 */
final class FtpsClient implements StorageClientInterface
{
    private const TIMEOUT_SECONDS = 30;

    public function __construct(private readonly DocumentStorageTarget $target)
    {
    }

    public function upload(string $contents, string $remotePath): void
    {
        $tmp = $this->writeTempFile($contents);

        try {
            $this->performUpload($tmp, $remotePath);
        } finally {
            fclose($tmp);
        }
    }

    public function testConnection(): string
    {
        $testFilename = '.materialverleih-verbindungstest-' . time() . '.txt';
        $contents = "Materialverleih-Verbindungstest vom " . date('d.m.Y H:i:s') . "\n";

        $this->upload($contents, $testFilename);

        // Löschen über CURLOPT_POSTQUOTE (nach erfolgreichem Transfer
        // ausgeführter FTP-Befehl) bei einem harmlosen No-Op-Transfer wäre
        // umständlich — einfacher: eigener Verbindungsaufbau nur für DELE.
        $deleted = $this->deleteFile($testFilename);
        if (!$deleted) {
            return 'Verbindung erfolgreich, Testdatei konnte aber nicht automatisch entfernt werden — bitte manuell aufräumen.';
        }

        return 'Verbindung erfolgreich — Testdatei wurde hochgeladen und wieder entfernt.';
    }

    /** @return resource */
    private function writeTempFile(string $contents)
    {
        $tmp = tmpfile();
        if ($tmp === false) {
            throw new StorageUploadException('Temporäre Datei für den FTPS-Upload konnte nicht angelegt werden.');
        }

        fwrite($tmp, $contents);
        fseek($tmp, 0);

        return $tmp;
    }

    /** @param resource $fileHandle */
    private function performUpload($fileHandle, string $remotePath): void
    {
        $size = fstat($fileHandle)['size'] ?? 0;

        $ch = curl_init();
        if ($ch === false) {
            throw new StorageUploadException('curl konnte nicht initialisiert werden.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->buildUrl($remotePath),
            CURLOPT_UPLOAD => true,
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
            CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR,
            CURLOPT_INFILE => $fileHandle,
            CURLOPT_INFILESIZE => $size,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_USERPWD => $this->target->username . ':' . ($this->target->password ?? ''),
            // FTPS-Zertifikate sind bei kleineren Hostern häufig
            // selbstsigniert bzw. nicht über die System-CA-Kette prüfbar —
            // anders als bei WebDAV gibt es für FTPS in dieser Funktion
            // bewusst keine eigene "unsicher erlauben"-Option, da
            // CURLOPT_USE_SSL=ALL bereits eine verschlüsselte Verbindung
            // erzwingt; die Zertifikatsprüfung folgt curls Systemstandard.
        ]);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($ok === false || $errno !== 0) {
            throw new StorageUploadException("FTPS-Upload fehlgeschlagen: {$error}");
        }
    }

    private function deleteFile(string $remotePath): bool
    {
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }

        // "cwd /" verhindert, dass ein DELE mit relativem Pfad fehlschlägt,
        // wenn der Server nach dem letzten Transfer bereits im Zielverzeichnis steht.
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->buildUrl(''),
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
            CURLOPT_USERPWD => $this->target->username . ':' . ($this->target->password ?? ''),
            CURLOPT_QUOTE => ['DELE ' . $this->remotePathWithoutBase($remotePath)],
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        return $ok !== false && $errno === 0;
    }

    private function remotePathWithoutBase(string $remotePath): string
    {
        $basePath = trim((string) $this->target->basePath, '/');

        return '/' . trim($basePath !== '' ? $basePath . '/' . trim($remotePath, '/') : trim($remotePath, '/'), '/');
    }

    private function buildUrl(string $remotePath): string
    {
        $host = (string) $this->target->host;
        $port = $this->target->port ?? 21;

        $basePath = trim((string) $this->target->basePath, '/');
        $path = trim($remotePath, '/');
        $fullPath = implode('/', array_filter([$basePath, $path], static fn (string $part) => $part !== ''));

        return "ftps://{$host}:{$port}/{$fullPath}";
    }
}
