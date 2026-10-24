<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;

/**
 * Handgeschriebener WebDAV-Client über curl (PUT für den Upload, MKCOL für
 * fehlende Zwischenverzeichnisse, DELETE für den Verbindungstest) — analog
 * im Stil zu App\Infrastructure\Mail\SmtpMailer (kein Composer-Paket
 * verfügbar). Nextcloud-kompatibel: base_path typischerweise
 * "/remote.php/dav/files/{username}/...".
 *
 * TLS-Verifikation bleibt IMMER aktiv, außer der Administrator hat für
 * dieses Ziellaufwerk explizit "Selbstsigniertes Zertifikat erlauben"
 * aktiviert (webdav_allow_self_signed) — siehe DocumentStorageTarget.
 */
final class WebDavClient implements StorageClientInterface
{
    private const TIMEOUT_SECONDS = 20;

    public function __construct(private readonly DocumentStorageTarget $target)
    {
    }

    public function upload(string $contents, string $remotePath): void
    {
        $this->ensureDirectoriesExist($remotePath);

        $url = $this->buildUrl($remotePath);
        $response = $this->request('PUT', $url, $contents);

        if ($response['errno'] !== 0) {
            throw new StorageUploadException("WebDAV-Upload fehlgeschlagen: {$response['error']}");
        }

        if ($response['httpCode'] < 200 || $response['httpCode'] >= 300) {
            throw new StorageUploadException("WebDAV-Server hat den Upload abgelehnt (HTTP {$response['httpCode']}).");
        }
    }

    public function testConnection(): string
    {
        $testFilename = '.materialverleih-verbindungstest-' . time() . '.txt';
        $contents = "Materialverleih-Verbindungstest vom " . date('d.m.Y H:i:s') . "\n";

        $this->upload($contents, $testFilename);

        $deleteResponse = $this->request('DELETE', $this->buildUrl($testFilename), null);
        if ($deleteResponse['errno'] !== 0 || ($deleteResponse['httpCode'] >= 300 && $deleteResponse['httpCode'] !== 404)) {
            return 'Verbindung erfolgreich, Testdatei konnte aber nicht automatisch entfernt werden — bitte manuell aufräumen.';
        }

        return 'Verbindung erfolgreich — Testdatei wurde hochgeladen und wieder entfernt.';
    }

    /**
     * Legt fehlende Zwischenverzeichnisse rekursiv per MKCOL an (WebDAV
     * verlangt, dass jeder Pfadteil einzeln existiert, ein PUT auf ein
     * nicht existierendes Verzeichnis schlägt sonst mit 409 Conflict fehl).
     * Ein 405 "Already exists" für ein bereits vorhandenes Verzeichnis wird
     * bewusst ignoriert.
     */
    private function ensureDirectoriesExist(string $remotePath): void
    {
        $segments = explode('/', trim(dirname($remotePath), '/'));
        $segments = array_filter($segments, static fn (string $segment) => $segment !== '' && $segment !== '.');

        $currentPath = '';
        foreach ($segments as $segment) {
            $currentPath .= '/' . $segment;
            $response = $this->request('MKCOL', $this->buildUrl(ltrim($currentPath, '/')), null);

            // 201 = angelegt, 405 = existiert bereits — beides in Ordnung.
            // Andere Fehler werden erst beim eigentlichen PUT sichtbar, um
            // hier nicht zu streng zu sein (manche Server melden abweichende Codes).
        }
    }

    private function buildUrl(string $remotePath): string
    {
        $scheme = $this->target->webdavUseHttps ? 'https' : 'http';
        $host = (string) $this->target->host;
        $port = $this->target->port;
        $hostPart = $host . ($port !== null ? ':' . $port : '');

        $basePath = trim((string) $this->target->basePath, '/');
        $path = trim($remotePath, '/');

        $fullPath = implode('/', array_filter([$basePath, $path], static fn (string $part) => $part !== ''));

        // Jedes Pfadsegment einzeln URL-kodieren, damit Schrägstriche als
        // Trenner erhalten bleiben (Leerzeichen/Sonderzeichen in Datei-
        // /Ordnernamen sind bei generierten Dokumentnamen sonst problematisch).
        $encodedSegments = array_map('rawurlencode', explode('/', $fullPath));

        return $scheme . '://' . $hostPart . '/' . implode('/', $encodedSegments);
    }

    /** @return array{errno: int, error: string, httpCode: int, body: string} */
    private function request(string $method, string $url, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new StorageUploadException('curl konnte nicht initialisiert werden.');
        }

        $headers = [];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/octet-stream';
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => !$this->target->webdavAllowSelfSigned,
            CURLOPT_SSL_VERIFYHOST => $this->target->webdavAllowSelfSigned ? 0 : 2,
        ]);

        if ($this->target->username !== null && $this->target->username !== '') {
            curl_setopt($ch, CURLOPT_USERPWD, $this->target->username . ':' . ($this->target->password ?? ''));
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'errno' => $errno,
            'error' => $error,
            'httpCode' => $httpCode,
            'body' => is_string($responseBody) ? $responseBody : '',
        ];
    }
}
