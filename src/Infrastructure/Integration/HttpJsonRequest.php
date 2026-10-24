<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration;

use RuntimeException;

/**
 * Sehr dünner, gemeinsam genutzter curl-Helfer für JSON-REST-Aufrufe gegen
 * die Google- und Meta-APIs (siehe Google\GoogleMerchantApiClient,
 * Google\GoogleBusinessApiClient, Whatsapp\WhatsappCatalogApiClient). Die
 * Anwendung hat bewusst keine HTTP-Client-Bibliothek (Guzzle o. Ä.) als
 * Abhängigkeit — bestehende Integrationen (S3Client, WebDavClient, …)
 * rufen curl ebenfalls direkt auf; dieser Helfer bündelt nur das für alle
 * drei neuen Integrationen identische "JSON rein, JSON raus"-Muster.
 */
final class HttpJsonRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $jsonBody
     * @return array{status: int, json: array<string, mixed>|null, raw: string}
     */
    public static function send(string $method, string $url, array $headers = [], ?array $jsonBody = null, int $timeoutSeconds = 20): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl konnte nicht initialisiert werden.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);

        if ($jsonBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_THROW_ON_ERROR));
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP-Anfrage fehlgeschlagen: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = null;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $json = $decoded;
            }
        }

        return ['status' => $status, 'json' => $json, 'raw' => $raw];
    }
}
