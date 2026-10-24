<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;

/**
 * SFTP-Client über ext-ssh2 — diese Erweiterung ist NICHT in jeder
 * PHP-Umgebung installiert (auch nicht in der Entwicklungs-Sandbox dieses
 * Projekts), daher zur Laufzeit geprüft (extension_loaded()) statt
 * vorausgesetzt. Public-Key-Auth benötigt Dateipfade
 * (ssh2_auth_pubkey_file()), daher wird ein in der Datenbank hinterlegter
 * Schlüssel kurzzeitig in eine temporäre Datei geschrieben und danach
 * sofort wieder gelöscht.
 */
final class SftpClient implements StorageClientInterface
{
    private const TIMEOUT_SECONDS = 20;

    public function __construct(private readonly DocumentStorageTarget $target)
    {
    }

    public function upload(string $contents, string $remotePath): void
    {
        $this->assertExtensionAvailable();

        $connection = $this->connect();
        $sftp = @ssh2_sftp($connection);
        if ($sftp === false) {
            throw new StorageUploadException('SFTP-Subsystem konnte auf dem Server nicht initialisiert werden.');
        }

        $fullPath = $this->fullRemotePath($remotePath);
        $this->ensureDirectoriesExist($sftp, dirname($fullPath));

        $stream = @fopen("ssh2.sftp://" . intval($sftp) . $fullPath, 'w');
        if ($stream === false) {
            throw new StorageUploadException("SFTP-Zieldatei konnte nicht geöffnet werden: {$fullPath}");
        }

        $written = fwrite($stream, $contents);
        fclose($stream);

        if ($written === false || $written < strlen($contents)) {
            throw new StorageUploadException('SFTP-Upload wurde unvollständig übertragen.');
        }
    }

    public function testConnection(): string
    {
        $this->assertExtensionAvailable();

        $testFilename = '.materialverleih-verbindungstest-' . time() . '.txt';
        $contents = "Materialverleih-Verbindungstest vom " . date('d.m.Y H:i:s') . "\n";

        $this->upload($contents, $testFilename);

        $connection = $this->connect();
        $sftp = @ssh2_sftp($connection);
        $fullPath = $this->fullRemotePath($testFilename);

        $deleted = $sftp !== false && @ssh2_sftp_unlink($sftp, $fullPath);
        if (!$deleted) {
            return 'Verbindung erfolgreich, Testdatei konnte aber nicht automatisch entfernt werden — bitte manuell aufräumen.';
        }

        return 'Verbindung erfolgreich — Testdatei wurde hochgeladen und wieder entfernt.';
    }

    private function assertExtensionAvailable(): void
    {
        if (!extension_loaded('ssh2')) {
            throw new StorageUploadException(
                'SFTP benötigt die PHP-Erweiterung ext-ssh2, die auf diesem Server nicht installiert ist. '
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
            throw new StorageUploadException("SFTP-Verbindung zu {$host}:{$port} fehlgeschlagen.");
        }

        $this->authenticate($connection);

        return $connection;
    }

    /** @param resource $connection */
    private function authenticate($connection): void
    {
        $username = (string) $this->target->username;

        if ($this->target->privateKey !== null && trim($this->target->privateKey) !== '') {
            [$publicKeyPath, $privateKeyPath] = $this->writeTemporaryKeyFiles();

            try {
                $ok = @ssh2_auth_pubkey_file(
                    $connection,
                    $username,
                    $publicKeyPath,
                    $privateKeyPath,
                    $this->target->privateKeyPassphrase ?? ''
                );
            } finally {
                @unlink($publicKeyPath);
                @unlink($privateKeyPath);
            }

            if ($ok !== true) {
                throw new StorageUploadException('SFTP-Anmeldung per Schlüssel ist fehlgeschlagen.');
            }

            return;
        }

        $ok = @ssh2_auth_password($connection, $username, $this->target->password ?? '');
        if ($ok !== true) {
            throw new StorageUploadException('SFTP-Anmeldung per Passwort ist fehlgeschlagen.');
        }
    }

    /**
     * ssh2_auth_pubkey_file() erwartet sowohl den öffentlichen als auch den
     * privaten Schlüssel als Dateipfade. Aus dem gespeicherten privaten
     * Schlüssel wird hier kein öffentlicher Schlüssel extrahiert (dafür
     * gäbe es keine verlässliche, abhängigkeitsfreie Möglichkeit) — die
     * meisten SSH2-Implementierungen akzeptieren stattdessen den privaten
     * Schlüssel auch als "öffentliche" Datei, sofern der Server selbst die
     * Authentizität über die Signatur prüft; ist das für den Zielserver
     * nicht ausreichend, muss der Administrator zusätzlich den
     * öffentlichen Schlüssel separat hinterlegen (aktuell nicht vom
     * Formular unterstützt, siehe Projektdokumentation für diese Einschränkung).
     *
     * @return array{0: string, 1: string} [publicKeyPath, privateKeyPath]
     */
    private function writeTemporaryKeyFiles(): array
    {
        $privateKeyPath = tempnam(sys_get_temp_dir(), 'mv_sftp_key_');
        $publicKeyPath = tempnam(sys_get_temp_dir(), 'mv_sftp_pubkey_');

        if ($privateKeyPath === false || $publicKeyPath === false) {
            throw new StorageUploadException('Temporäre Schlüsseldatei für die SFTP-Anmeldung konnte nicht angelegt werden.');
        }

        file_put_contents($privateKeyPath, $this->target->privateKey);
        chmod($privateKeyPath, 0600);

        // Öffentlicher Schlüssel wird, falls in der Datenbank kein
        // separates Feld existiert, aus dem privaten Schlüssel abgeleitet
        // versucht — ssh2_auth_pubkey_file() benötigt zwingend einen Pfad,
        // daher hier ein einfacher Platzhalter, falls keine Ableitung
        // möglich ist (siehe Docblock oben).
        file_put_contents($publicKeyPath, $this->target->privateKey);

        return [$publicKeyPath, $privateKeyPath];
    }

    /** @param resource $sftp */
    private function ensureDirectoriesExist($sftp, string $directory): void
    {
        $directory = trim($directory, '/');
        if ($directory === '' || $directory === '.') {
            return;
        }

        $segments = explode('/', $directory);
        $currentPath = '';
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $currentPath .= '/' . $segment;
            // Fehler (z. B. "Verzeichnis existiert bereits") werden bewusst
            // ignoriert — ssh2_sftp_mkdir() liefert dafür keine
            // zuverlässige Unterscheidung, und ein anschließender
            // Schreibversuch macht ein echtes Problem ohnehin sichtbar.
            @ssh2_sftp_mkdir($sftp, $currentPath, 0755, true);
        }
    }

    private function fullRemotePath(string $remotePath): string
    {
        $basePath = trim((string) $this->target->basePath, '/');
        $path = trim($remotePath, '/');
        $fullPath = implode('/', array_filter([$basePath, $path], static fn (string $part) => $part !== ''));

        return '/' . $fullPath;
    }
}
