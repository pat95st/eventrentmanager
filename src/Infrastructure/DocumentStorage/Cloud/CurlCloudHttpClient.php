<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Infrastructure\DocumentStorage\StorageUploadException;

/** curl-based CloudHttpClientInterface (the application deliberately has no HTTP client library). */
final class CurlCloudHttpClient implements CloudHttpClientInterface
{
    private const CONNECT_TIMEOUT_SECONDS = 20;
    private const TIMEOUT_SECONDS = 120;

    public function send(string $method, string $url, array $headers = [], ?string $body = null): CloudHttpResponse
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new StorageUploadException('curl konnte nicht initialisiert werden.');
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            // Prevent curl from adding "Expect: 100-continue" to larger uploads.
            CURLOPT_HTTPHEADER => array_merge($headers, ['Expect:']),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        }

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new StorageUploadException("Verbindung zum Cloud-Dienst fehlgeschlagen: {$error}");
        }

        return new CloudHttpResponse($status, is_string($responseBody) ? $responseBody : '', $responseHeaders);
    }
}
