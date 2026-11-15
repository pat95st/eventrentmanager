<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\OpenAi;

use App\Infrastructure\Integration\HttpJsonRequest;
use CURLStringFile;

/**
 * Dünner curl-Client für OpenAIs Bild-API (Modell konfigurierbar, siehe
 * App\Domain\Settings\AiAdImageSettings::effectiveImageModel()), analog zu
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
    private const GENERATIONS_URL = 'https://api.openai.com/v1/images/generations';
    private const EDITS_URL = 'https://api.openai.com/v1/images/edits';
    private const MODELS_URL = 'https://api.openai.com/v1/models';
    private const TIMEOUT_SECONDS = 120; // Bildgenerierung dauert oft 10-30s, großzügiger Puffer
    // Extra time per additional image when several are requested in one call.
    private const TIMEOUT_SECONDS_PER_EXTRA_IMAGE = 60;

    /**
     * @param int $count number of images to generate (>= 1)
     * @return list<string> Base64-kodierte PNG-Bilddaten, eines je Bild
     */
    public function generateFromText(string $apiKey, string $model, string $prompt, string $size, int $count = 1): array
    {
        if (!$this->supportsMultipleImagesPerRequest($model) && $count > 1) {
            $images = [];
            for ($i = 0; $i < $count; $i++) {
                $images = [...$images, ...$this->generateFromText($apiKey, $model, $prompt, $size, 1)];
            }

            return $images;
        }

        $response = HttpJsonRequest::send(
            method: 'POST',
            url: self::GENERATIONS_URL,
            headers: [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            jsonBody: [
                'model' => $model,
                'prompt' => $prompt,
                'size' => $size,
                'n' => $count,
            ],
            timeoutSeconds: $this->timeoutFor($count),
        );

        return $this->extractBase64Images($response, $count);
    }

    /**
     * @param int $count number of images to generate (>= 1)
     * @return list<string> Base64-kodierte PNG-Bilddaten, eines je Bild
     */
    public function generateFromImage(string $apiKey, string $model, string $prompt, string $size, string $referenceImageBytes, string $referenceImageMimeType, string $referenceImageFilename, int $count = 1): array
    {
        if (!$this->supportsMultipleImagesPerRequest($model) && $count > 1) {
            $images = [];
            for ($i = 0; $i < $count; $i++) {
                $images = [...$images, ...$this->generateFromImage($apiKey, $model, $prompt, $size, $referenceImageBytes, $referenceImageMimeType, $referenceImageFilename, 1)];
            }

            return $images;
        }

        $ch = curl_init(self::EDITS_URL);
        if ($ch === false) {
            throw new OpenAiImageException('curl konnte nicht initialisiert werden.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeoutFor($count),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => [
                'model' => $model,
                'prompt' => $prompt,
                'size' => $size,
                'n' => $count,
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

        return $this->extractBase64Images(['status' => $status, 'json' => is_array($json) ? $json : null, 'raw' => $raw], $count);
    }

    /**
     * Lists the image models available to the given API key (ids starting
     * with "gpt-image" or "dall-e"), sorted descending so newer versions come first.
     *
     * @return list<string>
     */
    public function listImageModels(string $apiKey): array
    {
        $response = HttpJsonRequest::send(
            method: 'GET',
            url: self::MODELS_URL,
            headers: ['Authorization' => 'Bearer ' . $apiKey],
            timeoutSeconds: 10,
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $message = $response['json']['error']['message'] ?? $response['raw'];
            throw new OpenAiImageException('OpenAI meldet einen Fehler (HTTP ' . $response['status'] . '): ' . $message);
        }

        $models = [];
        foreach ($response['json']['data'] ?? [] as $entry) {
            $id = is_array($entry) ? ($entry['id'] ?? null) : null;
            if (is_string($id) && preg_match('/^(gpt-image|dall-e)/', $id) === 1) {
                $models[] = $id;
            }
        }
        $models = array_values(array_unique($models));
        rsort($models, SORT_NATURAL);

        return $models;
    }

    /**
     * dall-e-3 only accepts n=1; for it, several images are requested one
     * after another instead.
     */
    private function supportsMultipleImagesPerRequest(string $model): bool
    {
        return !str_starts_with($model, 'dall-e-3');
    }

    private function timeoutFor(int $count): int
    {
        return self::TIMEOUT_SECONDS + self::TIMEOUT_SECONDS_PER_EXTRA_IMAGE * max(0, $count - 1);
    }

    /**
     * @param array{status: int, json: array<string, mixed>|null, raw: string} $response
     * @return list<string>
     */
    private function extractBase64Images(array $response, int $expectedCount): array
    {
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $message = $response['json']['error']['message'] ?? $response['raw'];
            throw new OpenAiImageException('OpenAI meldet einen Fehler (HTTP ' . $response['status'] . '): ' . $message);
        }

        $images = [];
        foreach ($response['json']['data'] ?? [] as $entry) {
            $b64 = is_array($entry) ? ($entry['b64_json'] ?? null) : null;
            if (is_string($b64) && $b64 !== '') {
                $images[] = $b64;
            }
        }
        if ($images === []) {
            throw new OpenAiImageException('OpenAI hat keine Bilddaten in der Antwort geliefert.');
        }

        // Never store more images than requested, even if the API returns extra.
        return array_slice($images, 0, $expectedCount);
    }
}
