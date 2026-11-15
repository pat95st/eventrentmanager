<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Infrastructure\DocumentStorage\StorageUploadException;

/**
 * Dropbox API v2 (https://www.dropbox.com/developers/documentation/http).
 * Dropbox addresses everything by path and creates missing parent folders
 * itself. Depending on the app type, paths are relative to the app folder
 * ("App folder") or to the whole Dropbox ("Full Dropbox").
 */
final class DropboxClient extends AbstractOAuthCloudClient
{
    private const API = 'https://api.dropboxapi.com/2/';
    private const CONTENT_API = 'https://content.dropboxapi.com/2/';
    /** Limit of a single files/upload call. */
    private const MAX_SIMPLE_UPLOAD_BYTES = 150 * 1024 * 1024;

    public function upload(string $contents, string $remotePath): void
    {
        $path = $this->fullPath($remotePath);
        if ($path === '') {
            throw new StorageUploadException('Es wurde kein Dateiname angegeben.');
        }
        if (strlen($contents) > self::MAX_SIMPLE_UPLOAD_BYTES) {
            throw new StorageUploadException('Dropbox: Dateien über 150 MB werden nicht unterstützt.');
        }

        $response = $this->authorized('POST', self::CONTENT_API . 'files/upload', [
            'Content-Type: application/octet-stream',
            // json_encode() escapes non-ASCII characters as \uXXXX, as required for this header.
            'Dropbox-API-Arg: ' . json_encode(['path' => $path, 'mode' => 'overwrite', 'autorename' => false, 'mute' => true], JSON_THROW_ON_ERROR),
        ], $contents);

        if (!$response->isSuccess()) {
            throw $this->failure('Upload', $response);
        }
    }

    public function createDirectory(string $remotePath): void
    {
        if ($this->segments($remotePath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }

        $response = $this->rpc('files/create_folder_v2', ['path' => $this->fullPath($remotePath), 'autorename' => false]);
        if ($response->isSuccess() || $this->errorStartsWith($response, 'path/conflict/folder')) {
            return;
        }

        throw $this->failure('Ordner anlegen', $response);
    }

    public function moveDirectory(string $fromPath, string $toPath): void
    {
        if ($this->segments($fromPath, false) === [] || $this->segments($toPath, false) === []) {
            throw new StorageUploadException('Es wurde kein Ordnerpfad angegeben.');
        }

        $response = $this->rpc('files/move_v2', [
            'from_path' => $this->fullPath($fromPath),
            'to_path' => $this->fullPath($toPath),
            'autorename' => false,
            'allow_ownership_transfer' => false,
        ]);
        if ($response->isSuccess()) {
            return;
        }
        if ($this->errorStartsWith($response, 'to/conflict')) {
            throw new StorageUploadException('Der Ordner "' . trim($toPath, '/') . '" existiert bereits.');
        }
        if ($this->errorStartsWith($response, 'from_lookup/not_found')) {
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
        $response = $this->rpc('files/delete_v2', ['path' => $this->fullPath($remotePath)]);
        if ($response->isSuccess() || $this->errorStartsWith($response, 'path_lookup/not_found')) {
            return;
        }

        throw $this->failure($action, $response);
    }

    /** @param array<string, mixed> $args */
    private function rpc(string $endpoint, array $args): CloudHttpResponse
    {
        return $this->authorizedJson('POST', self::API . $endpoint, $args);
    }

    /** Dropbox reports endpoint errors as HTTP 409 with a tag path in "error_summary". */
    private function errorStartsWith(CloudHttpResponse $response, string $prefix): bool
    {
        if ($response->status !== 409) {
            return false;
        }
        $summary = $response->json()['error_summary'] ?? '';

        return is_string($summary) && str_starts_with($summary, $prefix);
    }
}
