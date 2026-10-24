<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;

/**
 * SCP-Client über ext-ssh2 (siehe SftpClient für die Extension-Prüfung und
 * die Authentifizierungslogik, hier bewusst dupliziert statt geteilt, da
 * beide Clients unabhängig voneinander austauschbar/löschbar bleiben
 * sollen). WICHTIG: SCP kennt protokollbedingt KEIN Anlegen von
 * Verzeichnissen und KEIN Löschen — das Zielverzeichnis muss auf dem Server
 * bereits existieren, siehe Hinweistext im Admin-UI.
 */
final class ScpClient implements StorageClientInterface
{
    public function __construct(private readonly DocumentStorageTarget $target)
    {
    }

    public function upload(string $contents, string $remotePath): void
    {
        $this->assertExtensionAvailable();

        $connection = $this->connect();

        $tmpLocalFile = tempnam(sys_get_temp_dir(), 'mv_scp_upload_');
        if ($tmpLocalFile === false) {
            throw new StorageUploadException('Temporäre Datei für den SCP-Upload konnte nicht angelegt werden.');
        }

        try {
            file_put_contents($tmpLocalFile, $contents);

            $fullPath = $this->fullRemotePath($remotePath);
            $ok = @ssh2_scp_send($connection, $tmpLocalFile, $fullPath, 0644);

            if ($ok !== true) {
                throw new StorageUploadException(
                    "SCP-Upload nach {$fullPath} fehlgeschlagen — das Zielverzeichnis muss auf dem Server bereits existieren (SCP kann keine Verzeichnisse anlegen)."
                );
            }
        } finally {
            @unlink($tmpLocalFile);
        }
    }

    public function testConnection(): string
    {
        $this->assertExtensionAvailable();

        $testFilename = '.materialverleih-verbindungstest-' . time() . '.txt';
        $contents = "Materialverleih-Verbindungstest vom " . date('d.m.Y H:i:s') . "\n";

        $this->upload($contents, $testFilename);

        return 'Verbindung erfolgreich — Testdatei wurde hochgeladen. Testdatei konnte nicht automatisch entfernt werden (SCP kennt kein Löschen), bitte manuell aufräumen.';
    }

    private function assertExtensionAvailable(): void
    {
        if (!extension_loaded('ssh2')) {
            throw new StorageUploadException(
                'SCP benötigt die PHP-Erweiterung ext-ssh2, die auf diesem Server nicht installiert ist. '
                . 'Bitte beim Hosting-Anbieter aktivieren lassen oder ein anderes Protokoll (WebDAV/FTPS/S3) verwenden.'
            );
        }
    }

    /** @return resource */
    private function connect()
    {
        $host = (string) $this->target->host;
        $port = $this->target->port ?? 22;

        $connection = @ssh2_connect($host, $port);
        if ($connection === false) {
            throw new StorageUploadException("SCP-Verbindung zu {$host}:{$port} fehlgeschlagen.");
        }

        $username = (string) $this->target->username;

        if ($this->target->privateKey !== null && trim($this->target->privateKey) !== '') {
            $privateKeyPath = tempnam(sys_get_temp_dir(), 'mv_scp_key_');
            $publicKeyPath = tempnam(sys_get_temp_dir(), 'mv_scp_pubkey_');

            if ($privateKeyPath === false || $publicKeyPath === false) {
                throw new StorageUploadException('Temporäre Schlüsseldatei für die SCP-Anmeldung konnte nicht angelegt werden.');
            }

            try {
                file_put_contents($privateKeyPath, $this->target->privateKey);
                chmod($privateKeyPath, 0600);
                file_put_contents($publicKeyPath, $this->target->privateKey);

                $ok = @ssh2_auth_pubkey_file(
                    $connection,
                    $username,
                    $publicKeyPath,
                    $privateKeyPath,
                    $this->target->privateKeyPassphrase ?? ''
                );
            } finally {
                @unlink($privateKeyPath);
                @unlink($publicKeyPath);
            }

            if ($ok !== true) {
                throw new StorageUploadException('SCP-Anmeldung per Schlüssel ist fehlgeschlagen.');
            }

            return $connection;
        }

        $ok = @ssh2_auth_password($connection, $username, $this->target->password ?? '');
        if ($ok !== true) {
            throw new StorageUploadException('SCP-Anmeldung per Passwort ist fehlgeschlagen.');
        }

        return $connection;
    }

    private function fullRemotePath(string $remotePath): string
    {
        $basePath = trim((string) $this->target->basePath, '/');
        $path = trim($remotePath, '/');
        $fullPath = implode('/', array_filter([$basePath, $path], static fn (string $part) => $part !== ''));

        return '/' . $fullPath;
    }
}
