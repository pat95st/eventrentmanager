<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\OpenAi;

use App\Infrastructure\Integration\HttpJsonRequest;
use CURLStringFile;

/**
 * Dünner curl-Client für OpenAIs Bild-API (Modell "gpt-image-1"), analog zu
 * den übrigen direkten curl-Integrationen des Projekts (S3Client,
 * WebDavClient, HttpJsonRequest) — es gibt bewusst keine HTTP-Client-
 * Bibliothek (Guzzle o. Ä.) als Abhängigkeit.
 *
 * Reine Text-zu-Bild-Generierung ("images/generations") ist ein normaler
 * JSON-Request und nutzt daher App\Infrastructure\Integration\HttpJsonRequest.
 * Die Bild-zu-Bild-Variante ("images/edits", genutzt wenn ein vorhandenes
 * Artikelfoto als Referenz dient) benötigt dagegen multipart/form-data mit
 * einem echten Datei-Teil — das kann HttpJsonRequest nicht (nur JSON-Bodies),
 * daher hier ein eigener curl-Aufbau mit CURLStringFile (PHP 8.1+), das die
 * Bilddaten direkt aus dem Speicher ohne Zwischen-Temp-Datei hochladen kann.
 *
 * Generierte Bilder werden von OpenAI standardmäßig als Base64 ("b64_json")
 * zurückgegeben; eine temporär gehostete URL wäre eine zweite Option, aber
 * Base64 vermeidet einen zusätzlichen Download-Request und ist für die hier
 * erwarteten Bildgrößen unproblematisch.
 */
final class OpenAiImageClient
{
    private const MODEL = 'gpt-image-1';
    private const GENERATIONS_URL = 'https://api.openai.com/v1/images/generations';
    private const EDITS_URL = 'https://api.openai.com/v1/images/edits';
    private const TIMEOUT_SECONDS = 120; // Bildgenerierung dauert oft 10-30s, großzügiger Puffer

    /** @return string Base64-kodierte PNG-Bilddaten */
    public function generateFromText(string $apiKey, string $prompt, string $size): string
    {
        $response = HttpJsonRequest::send(
            method: 'POST',
            url: self::GENERATIONS_URL,
            headers: [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            jsonBody: [
                'model' => self::MODEL,
                'prompt' => $prompt,
                'size' => $size,
                'n' => 1,
            ],
            timeoutSeconds: self::TIMEOUT_SECONDS,
        );

        return $this->extractBase64Image($response);
    }

    /** @return string Base64-kodierte PNG-Bilddaten */
    public function generateFromImage(string $apiKey, string $prompt, string $size, string $referenceImageBytes, string $referenceImageMimeType, string $referenceImageFilename): string
    {
        $ch = curl_init(self::EDITS_URL);
        if ($ch === false) {
            throw new OpenAiImageException('curl konnte nicht initialisiert werden.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => [
                'model' => self::MODEL,
                'prompt' => $prompt,
                'size' => $size,
                'n' => 1,
                // CURLStringFile lädt die Bilddaten direkt aus dem Speicher
                // als multipart-Datei-Teil hoch, ohne eine Temp-Datei
                // anlegen zu müssen (seit PHP 8.1).
                'image' => new CURLStringFile($referenceImageBytes, $referenceImageFilename, $referenceImageMimeType),
            ],
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new OpenAiImageException('Anfrage an OpenAI fehlgeschlagen: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($raw, true);

        return $this->extractBase64Image(['status' => $status, 'json' => is_array($json) ? $json : null, 'raw' => $raw]);
    }

    /** @param array{status: int, json: array<string, mixed>|null, raw: string} $response */
    private function extractBase64Image(array $response): string
    {
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $message = $response['json']['error']['message'] ?? $response['raw'];
            throw new OpenAiImageException('OpenAI meldet einen Fehler (HTTP ' . $response['status'] . '): ' . $message);
        }

        $b64 = $response['json']['data'][0]['b64_json'] ?? null;
        if (!is_string($b64) || $b64 === '') {
            throw new OpenAiImageException('OpenAI hat keine Bilddaten in der Antwort geliefert.');
        }

        return $b64;
    }
}
