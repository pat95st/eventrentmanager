<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;

/**
 * Baut die URLs eines WebDAV-/Nextcloud-/ownCloud-Ziellaufwerks.
 *
 * - Protokoll "webdav": base_path ist der komplette URL-Pfad nach dem Host
 *   (wie seit Migration 044).
 * - Protokoll "nextcloud"/"owncloud" (Migration 148): der WebDAV-Pfad
 *   /remote.php/dav/files/<Benutzer>/ wird automatisch ergänzt, base_path ist
 *   nur noch ein optionaler Unterordner in den Dateien des Benutzers. Läuft
 *   die Cloud in einem Unterverzeichnis, darf es im Host-Feld mit angegeben
 *   werden ("example.org/cloud").
 */
final class WebDavEndpoint
{
    /** "https://host[:port][/unterverzeichnis]" ohne abschließenden Schrägstrich. */
    public static function rootUrl(DocumentStorageTarget $target): string
    {
        $host = preg_replace('#^https?://#i', '', trim((string) $target->host)) ?? '';
        $host = trim($host, '/');
        $prefix = '';
        if ($target->protocol->supportsSharing() && str_contains($host, '/')) {
            [$host, $prefix] = explode('/', $host, 2);
            $prefix = '/' . implode('/', array_map('rawurlencode', explode('/', trim($prefix, '/'))));
        }

        $scheme = $target->webdavUseHttps ? 'https' : 'http';

        return $scheme . '://' . $host . ($target->port !== null ? ':' . $target->port : '') . $prefix;
    }

    /**
     * Vollständige WebDAV-URL für einen Pfad relativ zum Basis-Pfad des
     * Ziellaufwerks ($includeBasePath = false: relativ zur WebDAV-Wurzel).
     */
    public static function fileUrl(DocumentStorageTarget $target, string $remotePath, bool $includeBasePath = true): string
    {
        $segments = [];
        if ($target->protocol->supportsSharing()) {
            $segments = ['remote.php', 'dav', 'files', (string) $target->username];
        }
        foreach ([$includeBasePath ? (string) $target->basePath : '', $remotePath] as $part) {
            foreach (explode('/', $part) as $segment) {
                if ($segment !== '') {
                    $segments[] = $segment;
                }
            }
        }

        // Jedes Pfadsegment einzeln URL-kodieren, damit Schrägstriche als
        // Trenner erhalten bleiben (Leerzeichen/Sonderzeichen in Datei-
        // /Ordnernamen sind sonst problematisch).
        return self::rootUrl($target) . '/' . implode('/', array_map('rawurlencode', $segments));
    }

    /** Pfad, wie ihn die OCS-Share-API erwartet: relativ zu den Dateien des Benutzers, mit führendem "/". */
    public static function userFilesPath(DocumentStorageTarget $target, string $remotePath): string
    {
        $segments = [];
        foreach ([(string) $target->basePath, $remotePath] as $part) {
            foreach (explode('/', $part) as $segment) {
                if ($segment !== '') {
                    $segments[] = $segment;
                }
            }
        }

        return '/' . implode('/', $segments);
    }
}
