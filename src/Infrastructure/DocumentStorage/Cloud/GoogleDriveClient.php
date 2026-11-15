<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Infrastructure\DocumentStorage\StorageUploadException;

/**
 * Google Drive API v3. Drive has no paths — every folder level is looked
 * up by name below its parent (starting at "root") and created on demand.
 *
 * With the "drive.file" scope the application only sees files and folders
 * it created itself; a folder of the same name created manually in Drive is
 * therefore not reused but created again by the application.
 */
final class GoogleDriveClient extends AbstractOAuthCloudClient
{
    private const API = 'https://www.googleapis.com/drive/v3/files';
    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3/files';
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

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
        if ($this->resolveFolder($to, false) !== null) {
            throw new StorageUploadException('Der Ordner "' . trim($toPath, '/') . '" existiert bereits.');
        }

        $newName = array_pop($to);
        array_pop($from);
        $oldParentId = $this->resolveFolder($from, false) ?? 'root';
        $newParentId = $this->resolveFolder($to, true) ?? 'root';

        $query = ['fields' => 'id'];
        if ($oldParentId !== $newParentId) {
            $query['addParents'] = $newParentId;
            $query['removeParents'] = $oldParentId;
        }
        $response = $this->authorizedJson('PATCH', self::API . '/' . rawurlencode($folderId) . '?' . http_build_query($query), ['name' => $newName]);
        if (!$response->isSuccess()) {
            throw $this->failure('Ordner umbenennen', $response);
        }
    }

    /** Moves the folder to the Drive trash (it can be restored there for 30 days). */
    public function deleteDirectory(string $remotePath): void
    {
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }
        $folderId = $this->resolveFolder($this->segments($remotePath), false);
        if ($folderId === null) {
            return;
        }

        $response = $this->authorizedJson('PATCH', self::API . '/' . rawurlencode($folderId) . '?fields=id', ['trashed' => true]);
        if (!$response->isSuccess() && $response->status !== 404) {
            throw $this->failure('Ordner löschen', $response);
        }
    }

    protected function deleteFile(string $remotePath): void
    {
        $segments = $this->segments($remotePath);
        $name = array_pop($segments);
        $parentId = $this->resolveFolder($segments, false);
        $fileId = $parentId !== null && $name !== null ? $this->findChild($parentId, $name, false) : null;
        if ($fileId === null) {
            return;
        }

        $response = $this->authorized('DELETE', self::API . '/' . rawurlencode($fileId));
        if (!$response->isSuccess() && $response->status !== 404) {
            throw $this->failure('Datei löschen', $response);
        }
    }

    /** Creates or overwrites the file, returns its ID. Uses a resumable upload (no size limit). */
    private function uploadFile(string $contents, string $remotePath): string
    {
        $segments = $this->segments($remotePath);
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Dateiname angegeben.');
        }
        $name = (string) array_pop($segments);
        $parentId = $this->resolveFolder($segments, true) ?? 'root';
        $existingId = $this->findChild($parentId, $name, false);
        $mime = self::contentType($name);

        $headers = ['X-Upload-Content-Type: ' . $mime, 'X-Upload-Content-Length: ' . strlen($contents)];
        if ($existingId !== null) {
            $init = $this->authorized('PATCH', self::UPLOAD_API . '/' . rawurlencode($existingId) . '?uploadType=resumable&fields=id', array_merge($headers, ['Content-Type: application/json; charset=UTF-8']), '{}');
        } else {
            $init = $this->authorized('POST', self::UPLOAD_API . '?uploadType=resumable&fields=id', array_merge($headers, ['Content-Type: application/json; charset=UTF-8']), json_encode([
                'name' => $name,
                'parents' => [$parentId],
            ], JSON_THROW_ON_ERROR));
        }
        $sessionUrl = $init->header('location');
        if (!$init->isSuccess() || $sessionUrl === null) {
            throw $this->failure('Upload', $init);
        }

        $response = $this->authorized('PUT', $sessionUrl, ['Content-Type: ' . $mime], $contents);
        $fileId = $response->json()['id'] ?? null;
        if (!$response->isSuccess() || !is_string($fileId)) {
            throw $this->failure('Upload', $response);
        }

        return $fileId;
    }

    /**
     * Folder ID for the given path ("root" for no segments); null if a level
     * does not exist and $create is false.
     *
     * @param list<string> $segments
     */
    private function resolveFolder(array $segments, bool $create): ?string
    {
        $parentId = 'root';
        foreach ($segments as $segment) {
            $childId = $this->findChild($parentId, $segment, true);
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

    private function findChild(string $parentId, string $name, bool $folder): ?string
    {
        $query = sprintf(
            "'%s' in parents and name = '%s' and trashed = false and mimeType %s '%s'",
            self::escapeQuery($parentId),
            self::escapeQuery($name),
            $folder ? '=' : '!=',
            self::FOLDER_MIME,
        );
        $response = $this->authorized('GET', self::API . '?' . http_build_query([
            'q' => $query,
            'fields' => 'files(id)',
            'pageSize' => 1,
            'spaces' => 'drive',
        ], '', '&', PHP_QUERY_RFC3986), ['Accept: application/json']);
        if (!$response->isSuccess()) {
            throw $this->failure('Suche', $response);
        }

        $id = $response->json()['files'][0]['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    private function createFolder(string $parentId, string $name): string
    {
        $response = $this->authorizedJson('POST', self::API . '?fields=id', [
            'name' => $name,
            'mimeType' => self::FOLDER_MIME,
            'parents' => [$parentId],
        ]);
        $id = $response->json()['id'] ?? null;
        if (!$response->isSuccess() || !is_string($id)) {
            throw $this->failure('Ordner anlegen', $response);
        }

        return $id;
    }

    /** Escaping for string literals in Drive search queries. */
    private static function escapeQuery(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
