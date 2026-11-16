<?php

declare(strict_types=1);

namespace App\Infrastructure\OAuth;

use App\Domain\Auth\ExternalLoginException;
use App\Domain\Auth\OAuthClientInterface;
use App\Domain\Settings\ExternalLoginSettings;
use RuntimeException;

/**
 * Server-side part of the OAuth 2.0 authorization code flow. The identity is
 * read from the userinfo endpoint (directly over TLS, authenticated with the
 * access token), so no ID token signature has to be validated.
 */
final class CurlOAuthClient implements OAuthClientInterface
{
    private const TIMEOUT_SECONDS = 10;

    public function discover(string $issuerUrl): array
    {
        $url = rtrim($issuerUrl, '/');
        if (!str_ends_with($url, '/.well-known/openid-configuration')) {
            $url .= '/.well-known/openid-configuration';
        }

        $response = $this->request('GET', $url, ['Accept' => 'application/json'], null);
        $json = $response['json'];
        if ($response['status'] !== 200 || $json === null) {
            throw new ExternalLoginException(sprintf('%s antwortet mit HTTP %d.', $url, $response['status']));
        }

        $endpoints = [];
        foreach (['authorization' => 'authorization_endpoint', 'token' => 'token_endpoint', 'userinfo' => 'userinfo_endpoint'] as $key => $field) {
            $value = $json[$field] ?? null;
            if (!is_string($value) || preg_match('#^https?://#i', $value) !== 1) {
                throw new ExternalLoginException(sprintf('In der Antwort von %s fehlt "%s".', $url, $field));
            }
            $endpoints[$key] = $value;
        }

        return $endpoints;
    }

    public function fetchClaims(ExternalLoginSettings $settings, string $code, string $redirectUri, string $codeVerifier): array
    {
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => (string) $settings->oauthClientId,
            'code_verifier' => $codeVerifier,
        ];
        if ($settings->oauthClientSecret !== null) {
            $form['client_secret'] = $settings->oauthClientSecret;
        }

        $token = $this->request('POST', (string) $settings->oauthTokenUrl, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], http_build_query($form));

        $accessToken = $token['json']['access_token'] ?? null;
        if ($token['status'] !== 200 || !is_string($accessToken) || $accessToken === '') {
            $reason = $token['json']['error_description'] ?? $token['json']['error'] ?? '';

            throw new ExternalLoginException(sprintf(
                'Token endpoint answered with HTTP %d%s.',
                $token['status'],
                is_string($reason) && $reason !== '' ? ' (' . $reason . ')' : '',
            ));
        }

        $userinfo = $this->request('GET', (string) $settings->oauthUserinfoUrl, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ], null);
        if ($userinfo['status'] !== 200 || $userinfo['json'] === null) {
            throw new ExternalLoginException(sprintf('Userinfo endpoint answered with HTTP %d.', $userinfo['status']));
        }

        return $userinfo['json'];
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, json: array<string, mixed>|null}
     */
    private function request(string $method, string $url, array $headers, ?string $body): array
    {
        if (!function_exists('curl_init')) {
            throw new ExternalLoginException('Die PHP-Erweiterung „curl“ ist auf diesem Server nicht installiert.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new ExternalLoginException('curl konnte nicht initialisiert werden.');
        }

        $headerLines = ['User-Agent: Materialverleih'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            // No redirects: the client secret and the access token must
            // only ever be sent to the configured endpoints.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if (!is_string($raw)) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new ExternalLoginException('Die Verbindung ist fehlgeschlagen: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        /** @var array<string, mixed>|null $json */
        $json = is_array($decoded) ? $decoded : null;

        return ['status' => $status, 'json' => $json];
    }
}
