<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\StorageProtocol;
use InvalidArgumentException;

/**
 * OAuth 2.0 endpoints and scopes of the cloud storage services
 * (StorageProtocol::usesOAuth()). All services use the authorization code
 * flow with a refresh token ("offline access").
 */
final class CloudOAuthEndpoints
{
    /** Google: only files and folders created by this application (no app verification needed). */
    public const GOOGLE_SCOPE = 'https://www.googleapis.com/auth/drive.file';
    public const MICROSOFT_SCOPE = 'offline_access Files.ReadWrite User.Read';

    public static function authorizeUrl(DocumentStorageTarget $target, string $redirectUri, string $state): string
    {
        $base = [
            'client_id' => (string) $target->oauthClientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ];

        return match ($target->protocol) {
            // Scopes are configured in the Dropbox app console ("Permissions").
            StorageProtocol::Dropbox => 'https://www.dropbox.com/oauth2/authorize?' . http_build_query($base + [
                'token_access_type' => 'offline',
            ], '', '&', PHP_QUERY_RFC3986),
            StorageProtocol::OneDrive => 'https://login.microsoftonline.com/' . rawurlencode(self::tenant($target)) . '/oauth2/v2.0/authorize?' . http_build_query($base + [
                'response_mode' => 'query',
                'scope' => self::MICROSOFT_SCOPE,
                'prompt' => 'select_account',
            ], '', '&', PHP_QUERY_RFC3986),
            // prompt=consent: Google only issues a refresh token on consent.
            StorageProtocol::GoogleDrive => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($base + [
                'scope' => self::GOOGLE_SCOPE,
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
            ], '', '&', PHP_QUERY_RFC3986),
            StorageProtocol::Box => 'https://account.box.com/api/oauth2/authorize?' . http_build_query($base, '', '&', PHP_QUERY_RFC3986),
            default => throw new InvalidArgumentException("{$target->protocol->label()} verwendet kein OAuth."),
        };
    }

    public static function tokenUrl(DocumentStorageTarget $target): string
    {
        return match ($target->protocol) {
            StorageProtocol::Dropbox => 'https://api.dropboxapi.com/oauth2/token',
            StorageProtocol::OneDrive => 'https://login.microsoftonline.com/' . rawurlencode(self::tenant($target)) . '/oauth2/v2.0/token',
            StorageProtocol::GoogleDrive => 'https://oauth2.googleapis.com/token',
            StorageProtocol::Box => 'https://api.box.com/oauth2/token',
            default => throw new InvalidArgumentException("{$target->protocol->label()} verwendet kein OAuth."),
        };
    }

    /** Scope parameter for token requests (only Microsoft expects it there). */
    public static function tokenScope(StorageProtocol $protocol): ?string
    {
        return $protocol === StorageProtocol::OneDrive ? self::MICROSOFT_SCOPE : null;
    }

    private static function tenant(DocumentStorageTarget $target): string
    {
        $tenant = trim((string) $target->oauthTenant);

        return $tenant !== '' ? $tenant : 'common';
    }
}
