<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use DateTimeImmutable;
use DateTimeZone;

/**
 * S3-kompatibler Client (echtes AWS S3 oder MinIO/Hetzner/Wasabi/…) mit
 * AWS Signature Version 4 (PUT Object / DELETE Object), von Hand über curl
 * implementiert (kein AWS-SDK verfügbar). Siehe signRequest() für den
 * vollständigen SigV4-Ablauf: Canonical Request -> String to Sign ->
 * Signing Key -> Signature -> Authorization-Header.
 */
final class S3Client implements StorageClientInterface
{
    private const TIMEOUT_SECONDS = 30;

    public function __construct(private readonly DocumentStorageTarget $target)
    {
    }

    public function upload(string $contents, string $remotePath): void
    {
        $this->request('PUT', $remotePath, $contents);
    }

    public function testConnection(): string
    {
        $testFilename = '.materialverleih-verbindungstest-' . time() . '.txt';
        $contents = "Materialverleih-Verbindungstest vom " . date('d.m.Y H:i:s') . "\n";

        $this->upload($contents, $testFilename);

        try {
            $this->request('DELETE', $testFilename, null);
        } catch (StorageUploadException) {
            return 'Verbindung erfolgreich, Testdatei konnte aber nicht automatisch entfernt werden — bitte manuell aufräumen.';
        }

        return 'Verbindung erfolgreich — Testdatei wurde hochgeladen und wieder entfernt.';
    }

    private function request(string $method, string $objectKey, ?string $body): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');

        $region = $this->target->s3Region !== null && trim($this->target->s3Region) !== ''
            ? trim($this->target->s3Region)
            : 'eu-central-1';
        $bucket = (string) $this->target->s3Bucket;
        $objectKey = ltrim($objectKey, '/');

        [$host, $canonicalUri, $url] = $this->buildUrl($bucket, $objectKey, $region);

        $payload = $body ?? '';
        $payloadHash = hash('sha256', $payload);

        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            '', // Canonical Query String (leer, kein Query-Parameter benötigt)
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "{$dateStamp}/{$region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = $this->calculateSignature($stringToSign, $dateStamp, $region);

        $accessKeyId = (string) $this->target->s3AccessKeyId;
        $authorizationHeader = "AWS4-HMAC-SHA256 Credential={$accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $headers = [
            'Host: ' . $host,
            'X-Amz-Content-Sha256: ' . $payloadHash,
            'X-Amz-Date: ' . $amzDate,
            'Authorization: ' . $authorizationHeader,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new StorageUploadException('curl konnte nicht initialisiert werden.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new StorageUploadException("S3-Anfrage fehlgeschlagen: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new StorageUploadException("S3-Server hat die Anfrage abgelehnt (HTTP {$httpCode}).");
        }
    }

    /** @return array{0: string, 1: string, 2: string} [host, canonicalUri, fullUrl] */
    private function buildUrl(string $bucket, string $objectKey, string $region): array
    {
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $objectKey)));
        $endpoint = $this->target->s3Endpoint !== null && trim($this->target->s3Endpoint) !== ''
            ? trim($this->target->s3Endpoint)
            : null;

        if ($this->target->s3UsePathStyle) {
            $host = $endpoint ?? "s3.{$region}.amazonaws.com";
            $canonicalUri = '/' . $bucket . '/' . $encodedKey;

            return [$host, $canonicalUri, "https://{$host}{$canonicalUri}"];
        }

        $host = $endpoint !== null ? "{$bucket}.{$endpoint}" : "{$bucket}.s3.{$region}.amazonaws.com";
        $canonicalUri = '/' . $encodedKey;

        return [$host, $canonicalUri, "https://{$host}{$canonicalUri}"];
    }

    private function calculateSignature(string $stringToSign, string $dateStamp, string $region): string
    {
        $secretKey = 'AWS4' . (string) $this->target->s3SecretAccessKey;

        $kDate = hash_hmac('sha256', $dateStamp, $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return hash_hmac('sha256', $stringToSign, $kSigning);
    }
}
