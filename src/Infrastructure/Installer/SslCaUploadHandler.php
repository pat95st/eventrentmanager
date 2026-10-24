<?php

declare(strict_types=1);

namespace App\Infrastructure\Installer;

use RuntimeException;

/**
 * Verarbeitet das optionale SSL-CA-Upload-Feld des Web-Installers
 * (public/install.php, Schritt "Datenbank") — als eigene, ohne einen
 * tatsächlichen HTTP-Upload testbare Klasse ausgelagert (siehe
 * Standalone-Tests). Ohne hochgeladene Datei wird null zurückgegeben
 * (Verbindung bleibt unverschlüsselt, Normalfall) — mit Datei wird sie nach
 * einer sehr groben Plausibilitätsprüfung (PEM-Format) dauerhaft unter
 * $destinationPath abgelegt (chmod 0600, analog zu database.yaml) und ihr
 * Pfad zurückgegeben, der dann unverändert nach database.yaml als "ssl_ca:"
 * geschrieben wird (siehe App\Infrastructure\Persistence\Pdo\Connection).
 */
final class SslCaUploadHandler
{
    /**
     * @param array{tmp_name?: string, error?: int}|null $uploadedFile Normalerweise $_FILES['ssl_ca_file'] ?? null.
     * @throws RuntimeException bei einem Upload-Fehler oder offensichtlich ungültigem Dateiinhalt
     */
    public static function handle(?array $uploadedFile, string $destinationPath): ?string
    {
        if (!is_array($uploadedFile) || ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Die SSL-CA-Zertifikatsdatei konnte nicht hochgeladen werden (Fehlercode ' . $uploadedFile['error'] . ').');
        }

        $content = is_string($uploadedFile['tmp_name'] ?? null) ? file_get_contents($uploadedFile['tmp_name']) : false;
        if ($content === false || trim($content) === '' || !str_contains($content, '-----BEGIN CERTIFICATE-----')) {
            throw new RuntimeException(
                'Die hochgeladene Datei scheint kein gültiges CA-Zertifikat zu sein (erwartet wird eine PEM-Datei, '
                . 'beginnend mit "-----BEGIN CERTIFICATE-----").'
            );
        }

        $directory = dirname($destinationPath);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Der Ordner "storage/" ist nicht beschreibbar, die SSL-CA-Zertifikatsdatei konnte nicht gespeichert werden.');
        }

        if (file_put_contents($destinationPath, $content) === false) {
            throw new RuntimeException('Die SSL-CA-Zertifikatsdatei konnte nicht gespeichert werden. Bitte die Dateirechte von storage/ prüfen.');
        }
        @chmod($destinationPath, 0600);

        return $destinationPath;
    }
}
