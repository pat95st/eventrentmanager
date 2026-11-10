<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Translation;

use App\Application\EventAudio\Translation\TranslationException;

/** Minimaler JSON-POST über cURL (austauschbar für Tests). */
class HttpJsonClient
{
    /**
     * @param array<string, mixed> $body
     * @param list<string> $headers
     * @return array{0: int, 1: array<string, mixed>|null, 2: string}
     */
    public function postJson(string $url, array $body, array $headers, int $timeoutSeconds = 20): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new TranslationException('Der Übersetzungsdienst ist nicht erreichbar: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string) $raw, true);

        return [$status, is_array($json) ? $json : null, (string) $raw];
    }
}
