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

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
        readfile($absolutePath);
        exit;
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
