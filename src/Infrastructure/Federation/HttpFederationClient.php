<?php

declare(strict_types=1);

namespace App\Infrastructure\Federation;

use App\Application\Federation\FederationClientInterface;
use App\Application\Federation\FederationException;

/**
 * curl-based client for the federation protocol. Redirects are not followed
 * so the bearer token is never sent to another host.
 */
final class HttpFederationClient implements FederationClientInterface
{
    public function __construct(private readonly int $timeoutSeconds = 15)
    {
    }

    public function request(string $method, string $baseUrl, string $path, ?string $token = null, ?array $body = null): array
    {
        $ch = curl_init(rtrim($baseUrl, '/') . $path);
        if ($ch === false) {
            throw new FederationException('curl konnte nicht initialisiert werden.');
        }

        $headers = ['Accept: application/json', 'User-Agent: Materialverleih-Federation/1'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new FederationException('Die Partner-Instanz ist nicht erreichbar (' . $error . ').');
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($json) && is_string($json['error'] ?? null) ? $json['error'] : 'HTTP-Status ' . $status;
            throw new FederationException(mb_substr($message, 0, 500), $status);
        }
        if (!is_array($json)) {
            throw new FederationException('Die Partner-Instanz hat keine gültige Antwort geschickt (ist die Adresse richtig?).', $status);
        }

        return $json;
    }
}
