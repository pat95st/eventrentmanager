<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\StorageProtocol;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use DateTimeImmutable;

final class CloudOAuthClient implements CloudOAuthClientInterface
{
    public function __construct(private readonly CloudHttpClientInterface $http = new CurlCloudHttpClient())
    {
    }

    public function exchangeCode(DocumentStorageTarget $target, string $redirectUri, string $code): OAuthTokenSet
    {
        $json = $this->tokenRequest($target, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $refreshToken = $json['refresh_token'] ?? null;
        if (!is_string($refreshToken) || $refreshToken === '') {
            throw new StorageUploadException(
                "{$target->protocol->label()} hat kein Refresh-Token geliefert. Bitte die Berechtigung der App im Konto entziehen und erneut verbinden."
            );
        }

        return $this->tokenSet($json, $refreshToken);
    }

    public function refresh(DocumentStorageTarget $target): OAuthTokenSet
    {
        if ($target->oauthRefreshToken === null || $target->oauthRefreshToken === '') {
            throw new StorageUploadException("Das Ziellaufwerk ist noch nicht mit {$target->protocol->label()} verbunden — bitte in den Einstellungen auf „Verbinden“ klicken.");
        }

        $json = $this->tokenRequest($target, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $target->oauthRefreshToken,
        ]);

        $refreshToken = $json['refresh_token'] ?? null;

        return $this->tokenSet($json, is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : $target->oauthRefreshToken);
    }

    public function accountLabel(DocumentStorageTarget $target, string $accessToken): ?string
    {
        $auth = 'Authorization: Bearer ' . $accessToken;

        try {
            $json = match ($target->protocol) {
                StorageProtocol::Dropbox => $this->http->send('POST', 'https://api.dropboxapi.com/2/users/get_current_account', [$auth, 'Content-Type: application/json'], 'null')->json(),
                StorageProtocol::OneDrive => $this->http->send('GET', 'https://graph.microsoft.com/v1.0/me?$select=displayName,userPrincipalName,mail', [$auth])->json(),
                StorageProtocol::GoogleDrive => $this->http->send('GET', 'https://www.googleapis.com/drive/v3/about?fields=user(displayName,emailAddress)', [$auth])->json(),
                StorageProtocol::Box => $this->http->send('GET', 'https://api.box.com/2.0/users/me?fields=name,login', [$auth])->json(),
                default => null,
            };
        } catch (StorageUploadException) {
            return null;
        }

        if ($json === null) {
            return null;
        }

        $candidates = match ($target->protocol) {
            StorageProtocol::Dropbox => [$json['email'] ?? null, $json['name']['display_name'] ?? null],
            StorageProtocol::OneDrive => [$json['mail'] ?? null, $json['userPrincipalName'] ?? null, $json['displayName'] ?? null],
            StorageProtocol::GoogleDrive => [$json['user']['emailAddress'] ?? null, $json['user']['displayName'] ?? null],
            StorageProtocol::Box => [$json['login'] ?? null, $json['name'] ?? null],
            default => [],
        };
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return mb_substr(trim($candidate), 0, 255);
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function tokenRequest(DocumentStorageTarget $target, array $fields): array
    {
        if ($target->oauthClientId === null || $target->oauthClientId === '' || $target->oauthClientSecret === null || $target->oauthClientSecret === '') {
            throw new StorageUploadException('Bitte zuerst Client-ID und Client-Secret der App hinterlegen.');
        }

        $fields['client_id'] = $target->oauthClientId;
        $fields['client_secret'] = $target->oauthClientSecret;
        $scope = CloudOAuthEndpoints::tokenScope($target->protocol);
        if ($scope !== null) {
            $fields['scope'] = $scope;
        }

        $response = $this->http->send(
            'POST',
            CloudOAuthEndpoints::tokenUrl($target),
            ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            http_build_query($fields, '', '&'),
        );
        $json = $response->json();

        if (!$response->isSuccess() || $json === null || !is_string($json['access_token'] ?? null)) {
            $message = '';
            if ($json !== null) {
                $message = (string) ($json['error_description'] ?? $json['error'] ?? '');
            }
            throw new StorageUploadException(
                "{$target->protocol->label()} hat die Token-Anfrage abgelehnt (HTTP {$response->status})" . ($message !== '' ? ": {$message}" : '.')
                . ($response->status === 400 || $response->status === 401 ? ' Ggf. die Verbindung in den Einstellungen erneuern.' : '')
            );
        }

        return $json;
    }

    /** @param array<string, mixed> $json */
    private function tokenSet(array $json, string $refreshToken): OAuthTokenSet
    {
        $expiresIn = isset($json['expires_in']) ? (int) $json['expires_in'] : 3600;

        return new OAuthTokenSet(
            (string) $json['access_token'],
            $refreshToken,
            new DateTimeImmutable('+' . max(60, $expiresIn) . ' seconds'),
        );
    }
}
