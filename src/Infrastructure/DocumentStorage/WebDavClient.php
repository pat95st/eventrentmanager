<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;

/**
 * Handgeschriebener WebDAV-Client über curl (PUT für den Upload, MKCOL für
 * fehlende Zwischenverzeichnisse, DELETE für den Verbindungstest) — analog
 * im Stil zu App\Infrastructure\Mail\SmtpMailer (kein Composer-Paket
 * verfügbar). Bedient auch die Protokolle "nextcloud"/"owncloud", bei denen
 * der WebDAV-Pfad automatisch ergänzt wird (siehe WebDavEndpoint).
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

    public function createDirectory(string $remotePath): void
    {
        $segments = array_values(array_filter(explode('/', $remotePath), static fn (string $segment) => $segment !== ''));
        if ($segments === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }

        // Bei Nextcloud/ownCloud ist der Basis-Pfad ein Ordner in den Dateien
        // des Benutzers und wird bei Bedarf gleich mit angelegt. Beim
        // allgemeinen WebDAV-Protokoll ist er Teil der Server-Adresse.
        $withBasePath = $this->target->protocol->supportsSharing();
        $currentPath = '';
        if ($withBasePath) {
            foreach (explode('/', (string) $this->target->basePath) as $segment) {
                if ($segment !== '') {
                    $currentPath .= '/' . $segment;
                    $this->request('MKCOL', WebDavEndpoint::fileUrl($this->target, $currentPath, false), null);
                }
            }
        }

        $response = null;
        foreach ($segments as $segment) {
            $currentPath .= '/' . $segment;
            $response = $this->request('MKCOL', WebDavEndpoint::fileUrl($this->target, $currentPath, !$withBasePath), null);
        }

        // Maßgeblich ist nur die letzte Ebene: 201 = angelegt, 405 = existiert bereits.
        if ($response['errno'] !== 0) {
            throw new StorageUploadException("Ordner konnte nicht angelegt werden: {$response['error']}");
        }
        if ($response['httpCode'] === 401 || $response['httpCode'] === 403) {
            throw new StorageUploadException("Der Server hat das Anlegen des Ordners abgelehnt (HTTP {$response['httpCode']}) — bitte Zugangsdaten und Schreibrechte prüfen.");
        }
        if (!in_array($response['httpCode'], [200, 201, 204, 405], true)) {
            throw new StorageUploadException("Ordner \"{$remotePath}\" konnte nicht angelegt werden (HTTP {$response['httpCode']}).");
        }
    }

    public function moveDirectory(string $fromPath, string $toPath): void
    {
        $from = trim($fromPath, '/');
        $to = trim($toPath, '/');
        if ($from === '' || $to === '') {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }

        $parent = dirname($to);
        if ($parent !== '.' && $parent !== '') {
            $this->createDirectory($parent);
        }

        $response = $this->request('MOVE', $this->buildUrl($from), null, [
            'Destination: ' . $this->buildUrl($to),
            'Overwrite: F',
        ]);

        if ($response['errno'] !== 0) {
            throw new StorageUploadException("Ordner konnte nicht umbenannt werden: {$response['error']}");
        }
        if ($response['httpCode'] === 412) {
            throw new StorageUploadException("Der Ordner \"{$to}\" existiert bereits.");
        }
        if ($response['httpCode'] === 404) {
            throw new StorageUploadException("Der Ordner \"{$from}\" wurde auf dem Speicherziel nicht gefunden (wurde er dort verschoben oder gelöscht?).");
        }
        if ($response['httpCode'] < 200 || $response['httpCode'] >= 300) {
            throw new StorageUploadException("Ordner konnte nicht umbenannt werden (HTTP {$response['httpCode']}).");
        }
    }

    public function deleteDirectory(string $remotePath): void
    {
        // Niemals den Basis-Pfad selbst löschen.
        if (trim($remotePath, '/') === '') {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }

        $response = $this->request('DELETE', $this->buildUrl($remotePath), null);

        if ($response['errno'] !== 0) {
            throw new StorageUploadException("Ordner konnte nicht gelöscht werden: {$response['error']}");
        }
        if ($response['httpCode'] !== 404 && ($response['httpCode'] < 200 || $response['httpCode'] >= 300)) {
            throw new StorageUploadException("Ordner konnte nicht gelöscht werden (HTTP {$response['httpCode']}).");
        }
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
            // 201 = angelegt, 405 = existiert bereits — beides in Ordnung.
            // Andere Fehler werden erst beim eigentlichen PUT sichtbar, um
            // hier nicht zu streng zu sein (manche Server melden abweichende Codes).
            $this->request('MKCOL', $this->buildUrl(ltrim($currentPath, '/')), null);
        }
    }

    private function buildUrl(string $remotePath): string
    {
        return WebDavEndpoint::fileUrl($this->target, $remotePath);
    }

    /**
     * @param list<string> $extraHeaders
     * @return array{errno: int, error: string, httpCode: int, body: string}
     */
    private function request(string $method, string $url, ?string $body, array $extraHeaders = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new StorageUploadException('curl konnte nicht initialisiert werden.');
        }

        $headers = $extraHeaders;
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
