<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Response
{
    public static function redirect(string $location): void
    {
        header('Location: ' . $location, true, 303);
        exit;
    }

    /**
     * Antwortet mit JSON (für die Passkey-Endpunkte, die vom Browser per
     * fetch() statt per Formular angesprochen werden, siehe
     * PasskeyController).
     *
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }

    public static function notFound(string $message = 'Nicht gefunden.'): void
    {
        http_response_code(404);
        echo View::render('errors/404', ['message' => $message]);
        exit;
    }

    /**
     * Generische 500-Fehlerseite für jeden nicht anderweitig behandelten
     * Throwable (siehe public/index.php). Zeigt bewusst KEINE technischen
     * Details an — die gehören ins Server-Error-Log, nicht auf die Seite.
     */
    public static function serverError(): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo View::render('errors/500');
        exit;
    }

    /**
     * Liefert eine Datei zum Download/Anzeigen aus (RMA-Dokumente liegen
     * bewusst außerhalb von public/, damit sie nicht ohne diesen Controller
     * erreichbar sind).
     */
    public static function file(string $absolutePath, string $downloadName, string $mimeType): void
    {
        if (!is_file($absolutePath)) {
            self::notFound('Die Datei wurde nicht gefunden.');
        }

        $size = (int) filesize($absolutePath);
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
        header('Accept-Ranges: bytes');

        // Teilabruf (Range) — Safari/iOS spielen Videos nur damit ab
        // (Hintergrundvideos, Migration 126).
        $range = self::parseRange($_SERVER['HTTP_RANGE'] ?? '', $size);
        if ($range === false) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        if ($range !== null) {
            [$start, $end] = $range;
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . (string) ($end - $start + 1));
            $handle = fopen($absolutePath, 'rb');
            if ($handle !== false) {
                fseek($handle, $start);
                $remaining = $end - $start + 1;
                while ($remaining > 0 && !feof($handle)) {
                    $chunk = fread($handle, min(65536, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
                fclose($handle);
            }
            exit;
        }

        header('Content-Length: ' . (string) $size);
        readfile($absolutePath);
        exit;
    }

    /**
     * Wertet einen einfachen "Range: bytes=…"-Header aus.
     *
     * @return array{0: int, 1: int}|null|false [Start, Ende] (inklusive), null = ganze Datei, false = nicht erfüllbar
     */
    public static function parseRange(string $header, int $size): array|null|false
    {
        if ($header === '' || preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) !== 1 || ($m[1] === '' && $m[2] === '')) {
            return null; // fehlend, mehrere Bereiche o. Ä.: ganze Datei
        }
        if ($size === 0) {
            return false;
        }
        if ($m[1] === '') {
            // "bytes=-500": die letzten 500 Bytes
            $start = max(0, $size - (int) $m[2]);
            $end = $size - 1;
        } else {
            $start = (int) $m[1];
            $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
        }
        if ($start >= $size || $start > $end) {
            return false;
        }

        return [$start, $end];
    }

    /**
     * Wie file(), aber cachebar (Firmenlogo, Hintergrundbild, Favicon): die
     * Session setzt sonst "no-store"/"Pragma: no-cache", wodurch die PWA das
     * Logo bei jedem Seitenaufruf neu lädt. Mit $immutable (URL enthält einen
     * Versionsparameter ?v=…, der sich bei jedem Upload ändert) darf der
     * Browser die Datei ein Jahr lang ohne Nachfrage verwenden, sonst fragt er
     * per ETag nach und bekommt bei unveränderter Datei nur ein 304.
     */
    public static function cachedFile(string $absolutePath, string $downloadName, string $mimeType, bool $immutable): void
    {
        if (!is_file($absolutePath)) {
            self::notFound('Die Datei wurde nicht gefunden.');
        }

        $etag = '"' . substr(md5($absolutePath . '|' . filesize($absolutePath) . '|' . filemtime($absolutePath)), 0, 16) . '"';
        header_remove('Pragma');
        header_remove('Expires');
        header('Cache-Control: ' . ($immutable ? 'private, max-age=31536000, immutable' : 'private, no-cache'));
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($absolutePath)) . ' GMT');

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch !== '' && in_array($etag, array_map('trim', explode(',', $ifNoneMatch)), true)) {
            http_response_code(304);
            exit;
        }

        self::file($absolutePath, $downloadName, $mimeType);
    }

    /**
     * Liefert eine bereits auf der Platte liegende Datei als ECHTEN Download
     * aus (Content-Disposition: attachment, anders als file() oben, das
     * "inline" setzt) — für die Backup-Export-ZIP (siehe
     * BackupSettingsController::export()): die soll im Browser immer
     * gespeichert werden, nicht versucht werden, direkt angezeigt zu
     * werden.
     */
    public static function downloadFile(string $absolutePath, string $downloadName, string $mimeType): void
    {
        if (!is_file($absolutePath)) {
            self::notFound('Die Datei wurde nicht gefunden.');
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
        readfile($absolutePath);
        exit;
    }

    /**
     * Liefert dynamisch erzeugte Binärdaten (z. B. ein generiertes PDF) als
     * Download aus, statt eine bereits gespeicherte Datei zu streamen.
     */
    public static function download(string $content, string $downloadName, string $mimeType): void
    {
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) strlen($content));
        header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
        echo $content;
        exit;
    }

    /**
     * Generische Antwort mit frei wählbarem Statuscode/Content-Type/weiteren
     * Headern — für den CalDAV-Server (207 Multi-Status/XML, 200/ICS mit
     * ETag, 401/403/404 mit Klartext, siehe CalDavController), der weder in
     * JSON- noch ins HTML-View-Schema passt.
     *
     * @param array<string, string> $headers
     */
    public static function raw(string $content, int $statusCode, string $contentType, array $headers = []): never
    {
        http_response_code($statusCode);
        header('Content-Type: ' . $contentType);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $content;
        exit;
    }
}
