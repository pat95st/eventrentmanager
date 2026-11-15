<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Infrastructure\DocumentStorage\StorageUploadException;

/**
 * Microsoft OneDrive (personal and OneDrive for Business) via Microsoft
 * Graph, addressing items by path relative to the drive root
 * (https://learn.microsoft.com/graph/onedrive-addressing-driveitems).
 */
final class OneDriveClient extends AbstractOAuthCloudClient
{
    private const API = 'https://graph.microsoft.com/v1.0/me/drive';
    /** Graph accepts simple PUT uploads up to 4 MB, larger files need an upload session. */
    private const MAX_SIMPLE_UPLOAD_BYTES = 4 * 1024 * 1024;
    /** Upload session chunks must be a multiple of 320 KiB. */
    private const CHUNK_BYTES = 10 * 320 * 1024;

    public function upload(string $contents, string $remotePath): void
    {
        $segments = $this->segments($remotePath);
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Dateiname angegeben.');
        }

        if (strlen($contents) <= self::MAX_SIMPLE_UPLOAD_BYTES) {
            $response = $this->authorized('PUT', $this->itemUrl($segments) . '/content', ['Content-Type: application/octet-stream'], $contents);
            if (!$response->isSuccess()) {
                throw $this->failure('Upload', $response);
            }

            return;
        }

        $this->uploadInChunks($contents, $segments);
    }

    public function createDirectory(string $remotePath): void
    {
        $segments = $this->segments($remotePath);
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }
        $this->ensureFolder($segments);
    }

    public function moveDirectory(string $fromPath, string $toPath): void
    {
        $from = $this->segments($fromPath);
        $to = $this->segments($toPath);
        if ($this->segments($fromPath, false) === [] || $this->segments($toPath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }

        $newName = array_pop($to);
        $parentId = $this->ensureFolder($to);

        $response = $this->authorizedJson('PATCH', $this->itemUrl($from), [
            'name' => $newName,
            'parentReference' => ['id' => $parentId],
            '@microsoft.graph.conflictBehavior' => 'fail',
        ]);
        if ($response->isSuccess()) {
            return;
        }
        if ($response->status === 409) {
            throw new StorageUploadException('Der Ordner "' . trim($toPath, '/') . '" existiert bereits.');
        }
        if ($response->status === 404) {
            throw new StorageUploadException('Der Ordner "' . trim($fromPath, '/') . '" wurde auf dem Speicherziel nicht gefunden (wurde er dort verschoben oder gelöscht?).');
        }

        throw $this->failure('Ordner umbenennen', $response);
    }

    public function deleteDirectory(string $remotePath): void
    {
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }
        $this->delete($remotePath, 'Ordner löschen');
    }

    protected function deleteFile(string $remotePath): void
    {
        $this->delete($remotePath, 'Datei löschen');
    }

    private function delete(string $remotePath, string $action): void
    {
        $response = $this->authorized('DELETE', $this->itemUrl($this->segments($remotePath)));
        if ($response->isSuccess() || $response->status === 404) {
            return;
        }

        throw $this->failure($action, $response);
    }

    /**
     * Creates the folder and all missing parents, returns the item ID of the folder.
     *
     * @param list<string> $segments
     */
    private function ensureFolder(array $segments): string
    {
        $existing = $this->authorized('GET', $this->itemUrl($segments) . '?$select=id,folder', ['Accept: application/json']);
        if ($existing->isSuccess()) {
            $json = $existing->json() ?? [];
            if (!isset($json['folder'])) {
                throw new StorageUploadException('"' . implode('/', $segments) . '" existiert bereits als Datei.');
            }

            return (string) ($json['id'] ?? '');
        }
        if ($existing->status !== 404) {
            throw $this->failure('Ordner abfragen', $existing);
        }

        $name = array_pop($segments);
        $this->ensureFolder($segments);

        $response = $this->authorizedJson('POST', $this->childrenUrl($segments), [
            'name' => $name,
            'folder' => new \stdClass(),
            '@microsoft.graph.conflictBehavior' => 'fail',
        ]);
        if ($response->isSuccess()) {
            return (string) ($response->json()['id'] ?? '');
        }
        if ($response->status === 409) {
            // Created concurrently in the meantime.
            $segments[] = (string) $name;

            return $this->ensureFolder($segments);
        }

        throw $this->failure('Ordner anlegen', $response);
    }

    /** @param list<string> $segments */
    private function uploadInChunks(string $contents, array $segments): void
    {
        $session = $this->authorizedJson('POST', $this->itemUrl($segments) . '/createUploadSession', [
            'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
        ]);
        $uploadUrl = $session->json()['uploadUrl'] ?? null;
        if (!$session->isSuccess() || !is_string($uploadUrl)) {
            throw $this->failure('Upload-Sitzung anlegen', $session);
        }

        $total = strlen($contents);
        for ($offset = 0; $offset < $total; $offset += self::CHUNK_BYTES) {
            $chunk = substr($contents, $offset, self::CHUNK_BYTES);
            $end = $offset + strlen($chunk) - 1;
            // The pre-authenticated upload URL must not receive an Authorization header.
            $response = $this->http->send('PUT', $uploadUrl, [
                'Content-Length: ' . strlen($chunk),
                "Content-Range: bytes {$offset}-{$end}/{$total}",
            ], $chunk);
            if (!$response->isSuccess()) {
                $this->http->send('DELETE', $uploadUrl);
                throw $this->failure('Upload', $response);
            }
        }
    }

    /** @param list<string> $segments */
    private function itemUrl(array $segments): string
    {
        if ($segments === []) {
            return self::API . '/root';
        }

        return self::API . '/root:/' . implode('/', array_map('rawurlencode', $segments)) . ':';
    }

    /** @param list<string> $segments */
    private function childrenUrl(array $segments): string
    {
        return $this->itemUrl($segments) . '/children';
    }
}
