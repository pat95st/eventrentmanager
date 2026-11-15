<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Infrastructure\DocumentStorage\StorageClientInterface;
use App\Infrastructure\DocumentStorage\StorageUploadException;

/**
 * Shared plumbing of the OAuth cloud clients: bearer authentication with a
 * single retry after a forced token refresh on HTTP 401, path handling
 * relative to the target's base path and the connection test.
 */
abstract class AbstractOAuthCloudClient implements StorageClientInterface
{
    public function __construct(
        protected readonly DocumentStorageTarget $target,
        protected readonly CloudHttpClientInterface $http,
        private readonly OAuthAccessTokenProvider $tokens,
    ) {
    }

    public function testConnection(): string
    {
        $testFilename = '.materialverleih-verbindungstest-' . time() . '.txt';
        $contents = 'Materialverleih-Verbindungstest vom ' . date('d.m.Y H:i:s') . "\n";

        $this->upload($contents, $testFilename);

        try {
            $this->deleteFile($testFilename);
        } catch (StorageUploadException) {
            return 'Verbindung erfolgreich, Testdatei konnte aber nicht automatisch entfernt werden — bitte manuell aufräumen.';
        }

        return 'Verbindung erfolgreich — Testdatei wurde hochgeladen und wieder entfernt.';
    }

    /** Deletes a single file (used by the connection test). */
    abstract protected function deleteFile(string $remotePath): void;

    /**
     * @param list<string> $headers
     * @throws StorageUploadException On connection errors only — HTTP status codes are left to the caller.
     */
    protected function authorized(string $method, string $url, array $headers = [], ?string $body = null): CloudHttpResponse
    {
        $response = $this->http->send($method, $url, array_merge($headers, ['Authorization: Bearer ' . $this->tokens->accessToken()]), $body);
        if ($response->status === 401) {
            $response = $this->http->send($method, $url, array_merge($headers, ['Authorization: Bearer ' . $this->tokens->accessToken(true)]), $body);
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    protected function authorizedJson(string $method, string $url, ?array $payload): CloudHttpResponse
    {
        return $this->authorized(
            $method,
            $url,
            ['Content-Type: application/json', 'Accept: application/json'],
            $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : null,
        );
    }

    /**
     * Path segments of base path + remote path ("." and empty segments
     * dropped). ".." is rejected so nothing outside the base folder is touched.
     *
     * @return list<string>
     */
    protected function segments(string $remotePath, bool $withBasePath = true): array
    {
        $raw = ($withBasePath ? (string) $this->target->basePath . '/' : '') . $remotePath;
        $segments = [];
        foreach (preg_split('#[/\\\\]+#', $raw) ?: [] as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new StorageUploadException('Pfade mit „..“ sind nicht erlaubt.');
            }
            $segments[] = $segment;
        }

        return $segments;
    }

    /** Absolute path within the cloud storage, "" for its root. */
    protected function fullPath(string $remotePath): string
    {
        $segments = $this->segments($remotePath);

        return $segments === [] ? '' : '/' . implode('/', $segments);
    }

    protected function failure(string $action, CloudHttpResponse $response): StorageUploadException
    {
        $detail = $this->errorDetail($response);

        return new StorageUploadException(
            "{$this->target->protocol->label()}: {$action} fehlgeschlagen (HTTP {$response->status})" . ($detail !== '' ? " — {$detail}" : '.')
        );
    }

    /** Human-readable error text from a provider's error response. */
    protected function errorDetail(CloudHttpResponse $response): string
    {
        $json = $response->json();
        if ($json === null) {
            return '';
        }
        $candidates = [
            $json['error_summary'] ?? null,           // Dropbox
            $json['error']['message'] ?? null,        // Microsoft Graph, Google
            $json['message'] ?? null,                 // Box
            $json['error_description'] ?? null,
            is_string($json['error'] ?? null) ? $json['error'] : null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return mb_substr(trim($candidate), 0, 300);
            }
        }

        return '';
    }

    /** Parent path ("" for the root) and last segment of a relative path. @return array{0: string, 1: string} */
    protected static function splitPath(string $remotePath): array
    {
        $trimmed = trim(str_replace('\\', '/', $remotePath), '/');
        $pos = strrpos($trimmed, '/');
        if ($pos === false) {
            return ['', $trimmed];
        }

        return [substr($trimmed, 0, $pos), substr($trimmed, $pos + 1)];
    }

    protected static function contentType(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
