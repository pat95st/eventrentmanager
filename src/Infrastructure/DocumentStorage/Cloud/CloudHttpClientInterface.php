<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Infrastructure\DocumentStorage\StorageUploadException;

/**
 * Minimal HTTP transport for the cloud storage clients (Dropbox, OneDrive,
 * Google Drive, Box) and their OAuth token requests. Abstracted so the
 * clients can be unit-tested without network access.
 */
interface CloudHttpClientInterface
{
    /**
     * @param list<string> $headers Complete header lines ("Name: value").
     * @throws StorageUploadException On connection errors (not on HTTP error status codes).
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null): CloudHttpResponse;
}
