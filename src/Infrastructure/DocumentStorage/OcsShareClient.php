<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\SharePermission;

/**
 * Freigabe-Links über die OCS-Share-API (/ocs/v2.php/apps/files_sharing/api/v1/shares),
 * die Nextcloud und ownCloud gleichermaßen anbieten. Angelegt wird immer ein
 * öffentlicher Link (shareType 3) — den Link verschickt die Anwendung selbst
 * per E-Mail an den Kunden (siehe SyncStorageFolderHandler). Die native
 * "per E-Mail teilen"-Freigabe gibt es nur bei Nextcloud und setzt dort einen
 * eigenen, funktionierenden Mailversand voraus.
 *
 * Handgeschrieben über curl wie WebDavClient; TLS-Verifikation bleibt aktiv,
 * außer "Selbstsigniertes Zertifikat erlauben" ist für das Ziellaufwerk gesetzt.
 */
final class OcsShareClient implements ShareClientInterface
{
    private const TIMEOUT_SECONDS = 20;
    private const SHARE_TYPE_PUBLIC_LINK = 3;

    public function __construct(private readonly DocumentStorageTarget $target)
    {
    }

    public function createPublicLink(string $remotePath, SharePermission $permission, ?string $password, string $label): PublicShare
    {
        $fields = [
            'path' => WebDavEndpoint::userFilesPath($this->target, $remotePath),
            'shareType' => (string) self::SHARE_TYPE_PUBLIC_LINK,
            'permissions' => (string) $permission->ocsPermissions(),
        ];
        if ($password !== null) {
            $fields['password'] = $password;
        }
        if ($label !== '') {
            // Nextcloud zeigt "label", ownCloud "name" als Bezeichnung des Links an.
            $fields['label'] = mb_substr($label, 0, 200);
            $fields['name'] = mb_substr($label, 0, 60);
        }

        $data = $this->request('POST', '', $fields);

        $id = isset($data['id']) ? (string) $data['id'] : '';
        $url = isset($data['url']) && is_string($data['url']) ? $data['url'] : '';
        if ($url === '' && isset($data['token']) && is_string($data['token']) && $data['token'] !== '') {
            $url = WebDavEndpoint::rootUrl($this->target) . '/index.php/s/' . rawurlencode($data['token']);
        }
        if ($id === '' || $url === '') {
            throw new StorageUploadException('Die Freigabe wurde angelegt, der Server hat aber keinen Link zurückgegeben.');
        }

        return new PublicShare($id, $url);
    }

    public function updatePermission(string $shareId, SharePermission $permission): void
    {
        $this->request('PUT', '/' . rawurlencode($shareId), ['permissions' => (string) $permission->ocsPermissions()]);
    }

    public function updatePassword(string $shareId, string $password): void
    {
        $this->request('PUT', '/' . rawurlencode($shareId), ['password' => $password]);
    }

    public function deleteShare(string $shareId): void
    {
        $this->request('DELETE', '/' . rawurlencode($shareId), null, allowNotFound: true);
    }

    /**
     * @param array<string, string>|null $fields
     * @return array<string, mixed> Inhalt von ocs.data
     */
    private function request(string $method, string $suffix, ?array $fields, bool $allowNotFound = false): array
    {
        $url = WebDavEndpoint::rootUrl($this->target) . '/ocs/v2.php/apps/files_sharing/api/v1/shares' . $suffix . '?format=json';

        $ch = curl_init($url);
        if ($ch === false) {
            throw new StorageUploadException('curl konnte nicht initialisiert werden.');
        }

        $headers = ['OCS-APIRequest: true', 'Accept: application/json'];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => !$this->target->webdavAllowSelfSigned,
            CURLOPT_SSL_VERIFYHOST => $this->target->webdavAllowSelfSigned ? 0 : 2,
            CURLOPT_USERPWD => $this->target->username . ':' . ($this->target->password ?? ''),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        ]);
        if ($fields !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($body)) {
            throw new StorageUploadException("Verbindung zur Freigabe-Schnittstelle fehlgeschlagen: {$error}");
        }

        return self::parseResponse($httpCode, $body, $allowNotFound);
    }

    /**
     * Wertet eine OCS-Antwort aus. Der eigentliche Status steht in
     * ocs.meta.statuscode (v2: wie HTTP, v1: 100 = OK); die Meldung des
     * Servers (z. B. "Passwort entspricht nicht der Richtlinie") wird an den
     * Benutzer durchgereicht.
     *
     * @return array<string, mixed>
     */
    public static function parseResponse(int $httpCode, string $body, bool $allowNotFound = false): array
    {
        if ($httpCode === 401) {
            throw new StorageUploadException('Anmeldung an der Freigabe-Schnittstelle fehlgeschlagen (HTTP 401) — bitte Benutzername und (App-)Passwort prüfen.');
        }

        $decoded = json_decode($body, true);
        $meta = is_array($decoded) && is_array($decoded['ocs']['meta'] ?? null) ? $decoded['ocs']['meta'] : null;
        $statusCode = $meta !== null ? (int) ($meta['statuscode'] ?? 0) : $httpCode;
        $message = $meta !== null && is_string($meta['message'] ?? null) ? trim($meta['message']) : '';

        if ($allowNotFound && ($statusCode === 404 || $httpCode === 404)) {
            return [];
        }

        $ok = $meta !== null
            ? ($statusCode === 100 || ($statusCode >= 200 && $statusCode < 300))
            : ($httpCode >= 200 && $httpCode < 300);
        if (!$ok || $httpCode >= 400) {
            $detail = $message !== '' && strtoupper($message) !== 'OK' ? $message : "HTTP {$httpCode}";

            throw new StorageUploadException("Der Server hat die Freigabe abgelehnt: {$detail}");
        }

        $data = is_array($decoded) ? ($decoded['ocs']['data'] ?? []) : [];

        return is_array($data) ? $data : [];
    }
}
