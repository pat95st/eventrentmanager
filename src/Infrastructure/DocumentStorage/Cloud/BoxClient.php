<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Infrastructure\DocumentStorage\StorageUploadException;

/**
 * Box Content API (https://developer.box.com/reference/). Like Google
 * Drive, Box addresses folders by ID (root folder "0"), so paths are
 * resolved level by level and missing folders are created.
 */
final class BoxClient extends AbstractOAuthCloudClient
{
    private const API = 'https://api.box.com/2.0';
    private const UPLOAD_API = 'https://upload.box.com/api/2.0';
    private const ROOT_ID = '0';
    private const PAGE_SIZE = 1000;
    /** Limit of a direct (non-chunked) upload. */
    private const MAX_SIMPLE_UPLOAD_BYTES = 50 * 1024 * 1024;

    public function upload(string $contents, string $remotePath): void
    {
        $this->uploadFile($contents, $remotePath);
    }

    public function createDirectory(string $remotePath): void
    {
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }
        $this->resolveFolder($this->segments($remotePath), true);
    }

    public function moveDirectory(string $fromPath, string $toPath): void
    {
        $from = $this->segments($fromPath);
        $to = $this->segments($toPath);
        if ($this->segments($fromPath, false) === [] || $this->segments($toPath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }

        $folderId = $this->resolveFolder($from, false);
        if ($folderId === null) {
            throw new StorageUploadException('Der Ordner "' . trim($fromPath, '/') . '" wurde auf dem Speicherziel nicht gefunden (wurde er dort verschoben oder gelöscht?).');
        }

        $newName = array_pop($to);
        $parentId = $this->resolveFolder($to, true) ?? self::ROOT_ID;

        $response = $this->authorizedJson('PUT', self::API . '/folders/' . rawurlencode($folderId) . '?fields=id', [
            'name' => $newName,
            'parent' => ['id' => $parentId],
        ]);
        if ($response->isSuccess()) {
            return;
        }
        if ($response->status === 409) {
            throw new StorageUploadException('Der Ordner "' . trim($toPath, '/') . '" existiert bereits.');
        }

        throw $this->failure('Ordner umbenennen', $response);
    }

    /** Moves the folder including its contents to the Box trash. */
    public function deleteDirectory(string $remotePath): void
    {
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }
        $folderId = $this->resolveFolder($this->segments($remotePath), false);
        if ($folderId === null) {
            return;
        }

        $response = $this->authorized('DELETE', self::API . '/folders/' . rawurlencode($folderId) . '?recursive=true');
        if (!$response->isSuccess() && $response->status !== 404) {
            throw $this->failure('Ordner löschen', $response);
        }
    }

    protected function deleteFile(string $remotePath): void
    {
        $segments = $this->segments($remotePath);
        $name = (string) array_pop($segments);
        $parentId = $this->resolveFolder($segments, false);
        $fileId = $parentId !== null ? $this->findChild($parentId, $name, 'file') : null;
        if ($fileId === null) {
            return;
        }

        $response = $this->authorized('DELETE', self::API . '/files/' . rawurlencode($fileId));
        if (!$response->isSuccess() && $response->status !== 404) {
            throw $this->failure('Datei löschen', $response);
        }
    }

    private function uploadFile(string $contents, string $remotePath): void
    {
        $segments = $this->segments($remotePath);
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Dateiname angegeben.');
        }
        if (strlen($contents) > self::MAX_SIMPLE_UPLOAD_BYTES) {
            throw new StorageUploadException('Box: Dateien über 50 MB werden nicht unterstützt.');
        }
        $name = (string) array_pop($segments);
        $parentId = $this->resolveFolder($segments, true) ?? self::ROOT_ID;
        $existingId = $this->findChild($parentId, $name, 'file');

        if ($existingId !== null) {
            // Upload a new version of the existing file.
            $url = self::UPLOAD_API . '/files/' . rawurlencode($existingId) . '/content';
            $attributes = ['name' => $name];
        } else {
            $url = self::UPLOAD_API . '/files/content';
            $attributes = ['name' => $name, 'parent' => ['id' => $parentId]];
        }

        // Box requires the "attributes" part before the "file" part.
        $boundary = 'materialverleih-' . bin2hex(random_bytes(12));
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"attributes\"\r\n\r\n"
            . json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\r\n"
            . "--{$boundary}\r\n"
            . 'Content-Disposition: form-data; name="file"; filename="' . str_replace(['"', "\r", "\n"], '_', $name) . "\"\r\n"
            . 'Content-Type: ' . self::contentType($name) . "\r\n\r\n"
            . $contents . "\r\n"
            . "--{$boundary}--\r\n";

        $response = $this->authorized('POST', $url, ['Content-Type: multipart/form-data; boundary=' . $boundary], $body);
        if (!$response->isSuccess()) {
            throw $this->failure('Upload', $response);
        }
    }

    /**
     * Folder ID for the given path ("0" for no segments); null if a level
     * does not exist and $create is false.
     *
     * @param list<string> $segments
     */
    private function resolveFolder(array $segments, bool $create): ?string
    {
        $parentId = self::ROOT_ID;
        foreach ($segments as $segment) {
            $childId = $this->findChild($parentId, $segment, 'folder');
            if ($childId === null) {
                if (!$create) {
                    return null;
                }
                $childId = $this->createFolder($parentId, $segment);
            }
            $parentId = $childId;
        }

        return $parentId;
    }

    /** @param 'file'|'folder' $type */
    private function findChild(string $parentId, string $name, string $type): ?string
    {
        // Box treats names case-insensitively when checking for conflicts.
        $needle = mb_strtolower($name);
        $offset = 0;
        do {
            $response = $this->authorized('GET', self::API . '/folders/' . rawurlencode($parentId) . '/items?' . http_build_query([
                'fields' => 'id,name,type',
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
            ]), ['Accept: application/json']);
            if (!$response->isSuccess()) {
                throw $this->failure('Ordner abfragen', $response);
            }
            $json = $response->json() ?? [];
            $entries = is_array($json['entries'] ?? null) ? $json['entries'] : [];
            foreach ($entries as $entry) {
                if (($entry['type'] ?? null) === $type && is_string($entry['name'] ?? null) && mb_strtolower($entry['name']) === $needle) {
                    return (string) $entry['id'];
                }
            }
            $offset += self::PAGE_SIZE;
            $total = (int) ($json['total_count'] ?? 0);
        } while ($entries !== [] && $offset < $total);

        return null;
    }

    private function createFolder(string $parentId, string $name): string
    {
        $response = $this->authorizedJson('POST', self::API . '/folders?fields=id', [
            'name' => $name,
            'parent' => ['id' => $parentId],
        ]);
        if ($response->isSuccess() && is_string($response->json()['id'] ?? null)) {
            return (string) $response->json()['id'];
        }
        if ($response->status === 409) {
            // Created concurrently in the meantime.
            $existing = $this->findChild($parentId, $name, 'folder');
            if ($existing !== null) {
                return $existing;
            }
        }

        throw $this->failure('Ordner anlegen', $response);
    }
}
