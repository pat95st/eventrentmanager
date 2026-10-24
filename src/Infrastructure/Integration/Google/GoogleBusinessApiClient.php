<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Google;

use App\Infrastructure\Integration\HttpJsonRequest;
use DateTimeImmutable;
use RuntimeException;

/**
 * Spricht die Google Business Profile API an (früher "Google My Business
 * API"). Anders als Merchant Center läuft die Authentifizierung hier per
 * OAuth2-Autorisierungscode-Flow mit einem echten Google-Konto, das das
 * Unternehmensprofil verwaltet — siehe connectUrl()/exchangeCode().
 *
 * WICHTIG (siehe auch GoogleBusinessSettings-Docblock): Google bietet für
 * einzelne "Produkte" im Unternehmensprofil keine öffentliche API. Diese
 * Klasse legt daher pro freigeschaltetem Artikel einen "Beitrag" (Local
 * Post) an — das ist die einzige Möglichkeit, einen Artikel über die API
 * im Unternehmensprofil sichtbar zu machen. Google schränkt den Zugriff
 * auf die Local-Posts-API seit einigen Jahren zunehmend ein (teils nur für
 * freigeschaltete Partner-Projekte) — schlägt ein Aufruf mit HTTP 403
 * fehl, ist das ein Hinweis darauf, dass das eigene Google-Cloud-Projekt
 * dafür nicht freigeschaltet ist; das lässt sich von hier aus nicht
 * umgehen, siehe Fehlermeldung.
 */
final class GoogleBusinessApiClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const ACCOUNTS_API = 'https://mybusinessaccountmanagement.googleapis.com/v1';
    private const INFO_API = 'https://mybusinessbusinessinformation.googleapis.com/v1';
    private const POSTS_API = 'https://mybusiness.googleapis.com/v4';
    private const SCOPE = 'https://www.googleapis.com/auth/business.manage';

    public function connectUrl(string $clientId, string $redirectUri, string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /** @return array{accessToken: string, refreshToken: string, expiresAt: DateTimeImmutable} */
    public function exchangeCode(string $clientId, string $clientSecret, string $redirectUri, string $code): array
    {
        $response = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
        ]);

        if (!isset($response['refresh_token'])) {
            throw new RuntimeException('Google hat kein Refresh-Token geliefert. Bitte die Verbindung trennen und erneut verbinden (ggf. vorher unter myaccount.google.com > Sicherheit > Apps mit Kontozugriff die Berechtigung dieser Anwendung entziehen, damit Google beim nächsten Mal wieder ein Refresh-Token ausstellt).');
        }

        return [
            'accessToken' => (string) $response['access_token'],
            'refreshToken' => (string) $response['refresh_token'],
            'expiresAt' => new DateTimeImmutable('+' . ((int) ($response['expires_in'] ?? 3600)) . ' seconds'),
        ];
    }

    /** @return array{accessToken: string, expiresAt: DateTimeImmutable} */
    public function refreshAccessToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        $response = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        return [
            'accessToken' => (string) $response['access_token'],
            'expiresAt' => new DateTimeImmutable('+' . ((int) ($response['expires_in'] ?? 3600)) . ' seconds'),
        ];
    }

    /** Liefert den Resource-Namen des ersten verfügbaren Kontos, z. B. "accounts/1234567890". */
    public function findFirstAccountId(string $accessToken): ?string
    {
        $response = HttpJsonRequest::send('GET', self::ACCOUNTS_API . '/accounts', $this->authHeader($accessToken));

        $accounts = $response['json']['accounts'] ?? [];
        if (!is_array($accounts) || count($accounts) === 0) {
            return null;
        }

        $first = $accounts[0];

        return is_array($first) && isset($first['name']) ? (string) $first['name'] : null;
    }

    /** Liefert den Resource-Namen des ersten Standorts des Kontos, z. B. "accounts/.../locations/9876543210". */
    public function findFirstLocationId(string $accessToken, string $accountId): ?string
    {
        $response = HttpJsonRequest::send(
            'GET',
            self::INFO_API . '/' . $accountId . '/locations?readMask=name',
            $this->authHeader($accessToken)
        );

        $locations = $response['json']['locations'] ?? [];
        if (!is_array($locations) || count($locations) === 0) {
            return null;
        }

        $first = $locations[0];

        return is_array($first) && isset($first['name']) ? (string) $first['name'] : null;
    }

    /**
     * Legt einen "Neu im Sortiment"-Beitrag für den Standort an.
     *
     * @return array{name: string} Resource-Name des angelegten Beitrags
     */
    public function createProductPost(string $accessToken, string $locationId, string $summary, string $productUrl, ?string $imageUrl): array
    {
        $body = [
            'languageCode' => 'de',
            'summary' => $summary,
            'topicType' => 'STANDARD',
            'callToAction' => ['actionType' => 'LEARN_MORE', 'url' => $productUrl],
        ];
        if ($imageUrl !== null) {
            $body['media'] = [['mediaFormat' => 'PHOTO', 'sourceUrl' => $imageUrl]];
        }

        $response = HttpJsonRequest::send(
            'POST',
            self::POSTS_API . '/' . $locationId . '/localPosts',
            array_merge($this->authHeader($accessToken), ['Content-Type' => 'application/json']),
            $body
        );

        if ($response['status'] >= 400 || $response['json'] === null || !isset($response['json']['name'])) {
            throw new RuntimeException($this->describeError('Beitrag konnte nicht im Google Unternehmensprofil veröffentlicht werden', $response));
        }

        return ['name' => (string) $response['json']['name']];
    }

    public function deletePost(string $accessToken, string $postResourceName): void
    {
        $response = HttpJsonRequest::send('DELETE', self::POSTS_API . '/' . $postResourceName, $this->authHeader($accessToken));

        if ($response['status'] >= 400 && $response['status'] !== 404) {
            throw new RuntimeException($this->describeError('Beitrag konnte im Google Unternehmensprofil nicht gelöscht werden', $response));
        }
    }

    /** @param array<string, string> $fields */
    private function tokenRequest(array $fields): array
    {
        $ch = curl_init(self::TOKEN_URL);
        if ($ch === false) {
            throw new RuntimeException('curl konnte nicht initialisiert werden.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => http_build_query($fields),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Token-Anfrage an Google ist fehlgeschlagen.');
        }

        $decoded = json_decode((string) $raw, true);
        if ($status >= 400 || !is_array($decoded) || !isset($decoded['access_token'])) {
            $message = is_array($decoded) && isset($decoded['error_description']) ? (string) $decoded['error_description'] : (string) $raw;

            throw new RuntimeException('Google hat die Token-Anfrage abgelehnt: ' . $message);
        }

        return $decoded;
    }

    /** @return array<string, string> */
    private function authHeader(string $accessToken): array
    {
        return ['Authorization' => 'Bearer ' . $accessToken];
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
}
