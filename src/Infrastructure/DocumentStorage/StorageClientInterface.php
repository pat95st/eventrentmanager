<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

/**
 * Gemeinsame Schnittstelle aller Protokoll-Clients (WebDavClient, FtpsClient,
 * SftpClient, ScpClient, S3Client) — siehe StorageClientFactory für die
 * Auswahl anhand von DocumentStorageTarget::$protocol.
 */
interface StorageClientInterface
{
    /**
     * @param string $remotePath Bereits vollständig aufgelöster Zielpfad
     *        (Platzhalter bereits ersetzt, siehe DocumentStoragePathResolver),
     *        relativ zum base_path/Bucket des Ziellaufwerks.
     * @throws StorageUploadException Bei jedem Verbindungs-/Auth-/Übertragungsfehler.
     */
    public function upload(string $contents, string $remotePath): void;

    /**
     * Lädt eine kleine Testdatei in die Wurzel des Ziellaufwerks hoch und
     * versucht sie anschließend wieder zu entfernen (siehe "Verbindung
     * testen" im Admin-UI). Gibt einen für den Administrator verständlichen
     * Ergebnistext zurück (z. B. einen Hinweis, falls das Protokoll kein
     * automatisches Löschen unterstützt, siehe ScpClient).
     *
     * @throws StorageUploadException Bei einem fehlgeschlagenen Verbindungs-/Upload-Versuch.
     */
    public function testConnection(): string;
}
