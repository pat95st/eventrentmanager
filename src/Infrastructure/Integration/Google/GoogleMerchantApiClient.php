<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Google;

use App\Infrastructure\Integration\HttpJsonRequest;
use RuntimeException;

/**
 * Spricht die Google Content API for Shopping (Merchant Center) v2.1 an.
 * Die Authentifizierung läuft über einen Google-Service-Account: aus der
 * JSON-Schlüsseldatei wird ein signiertes JWT gebaut und gegen Googles
 * OAuth2-Token-Endpunkt gegen ein kurzlebiges Access-Token getauscht (RFC
 * 7523, "JWT Bearer Token Flow") — dafür reicht PHPs eingebautes openssl
 * (openssl_sign), es wird keine JWT-Bibliothek benötigt.
 *
 * Referenz: https://developers.google.com/shopping-content/reference/rest
 */
final class GoogleMerchantApiClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_BASE = 'https://shoppingcontent.googleapis.com/content/v2.1';
    private const SCOPE = 'https://www.googleapis.com/auth/content';

    /**
     * Legt ein Produkt an oder aktualisiert es (products.insert ist in der
     * Content API zugleich ein Upsert über die offerId).
     *
     * @param array<string, mixed> $product Content-API-"Product"-Resource, siehe GoogleMerchantChannelPublisher
     * @return array{id: string} vom Server vergebene Produkt-ID (Format "online:de:DE:<offerId>")
     */
    public function upsertProduct(string $serviceAccountJson, string $merchantId, array $product): array
    {
        $accessToken = $this->fetchAccessToken($serviceAccountJson);

        $response = HttpJsonRequest::send(
            'POST',
            self::API_BASE . '/' . rawurlencode($merchantId) . '/products',
            ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            $product
        );

        if ($response['status'] >= 400 || $response['json'] === null || !isset($response['json']['id'])) {
            throw new RuntimeException($this->describeError('Produkt konnte nicht an Google Merchant Center übermittelt werden', $response));
        }

        /** @var string $id */
        $id = $response['json']['id'];

        return ['id' => $id];
    }

    public function deleteProduct(string $serviceAccountJson, string $merchantId, string $productId): void
    {
        $accessToken = $this->fetchAccessToken($serviceAccountJson);

        $response = HttpJsonRequest::send(
            'DELETE',
            self::API_BASE . '/' . rawurlencode($merchantId) . '/products/' . rawurlencode($productId),
            ['Authorization' => 'Bearer ' . $accessToken]
        );

        // 404 ist beim Entfernen unkritisch (Produkt war z. B. schon manuell gelöscht).
        if ($response['status'] >= 400 && $response['status'] !== 404) {
            throw new RuntimeException($this->describeError('Produkt konnte nicht aus Google Merchant Center entfernt werden', $response));
        }
    }

    private function fetchAccessToken(string $serviceAccountJson): string
    {
        $credentials = json_decode($serviceAccountJson, true);
        if (!is_array($credentials) || !isset($credentials['client_email'], $credentials['private_key']) || !is_string($credentials['client_email']) || !is_string($credentials['private_key'])) {
            throw new RuntimeException('Die hinterlegte Service-Account-Datei ist keine gültige Google-Zugangsdaten-Datei (client_email/private_key fehlen).');
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsignedJwt = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
            . '.' . self::base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));

        $signature = '';
        $signed = openssl_sign($unsignedJwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        if (!$signed) {
            throw new RuntimeException('JWT konnte mit dem privaten Schlüssel der Service-Account-Datei nicht signiert werden.');
        }

        $jwt = $unsignedJwt . '.' . self::base64UrlEncode($signature);

        $ch = curl_init(self::TOKEN_URL);
        if ($ch === false) {
            throw new RuntimeException('curl konnte nicht initialisiert werden.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Zugangs-Token konnte nicht von Google abgerufen werden.');
        }

        $decoded = json_decode((string) $raw, true);
        if ($status >= 400 || !is_array($decoded) || !isset($decoded['access_token']) || !is_string($decoded['access_token'])) {
            $message = is_array($decoded) && isset($decoded['error_description']) ? (string) $decoded['error_description'] : (string) $raw;

            throw new RuntimeException('Google hat das Service-Account-Zugangs-Token abgelehnt: ' . $message);
        }

        return $decoded['access_token'];
    }

    /** @param array{status: int, json: array<string, mixed>|null, raw: string} $response */
    private function describeError(string $prefix, array $response): string
    {
        $detail = $response['json']['error']['message'] ?? $response['raw'];
        if (is_array($detail)) {
            $detail = json_encode($detail);
        }

        return $prefix . ' (HTTP ' . $response['status'] . '): ' . $detail;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
