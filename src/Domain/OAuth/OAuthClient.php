<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use DateTimeImmutable;

/**
 * Client registered via dynamic client registration (RFC 7591), e.g.
 * claude.ai or Claude Desktop. Public clients (token_endpoint_auth_method
 * "none") have no secret and rely on PKCE; confidential clients get a secret
 * of which only the hash is stored.
 */
final class OAuthClient
{
    public const AUTH_NONE = 'none';
    public const AUTH_SECRET_POST = 'client_secret_post';
    public const AUTH_SECRET_BASIC = 'client_secret_basic';

    /** @param list<string> $redirectUris */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $redirectUris,
        public readonly ?string $clientSecretHash,
        public readonly string $tokenEndpointAuthMethod,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt = null,
    ) {
    }

    public function isConfidential(): bool
    {
        return $this->tokenEndpointAuthMethod !== self::AUTH_NONE;
    }

    /**
     * Exact match against a registered redirect URI; for loopback URIs
     * (http://localhost / 127.0.0.1 / [::1]) any port is accepted, as native
     * apps pick a free port at runtime (RFC 8252, section 7.3).
     */
    public function allowsRedirectUri(string $uri): bool
    {
        foreach ($this->redirectUris as $registered) {
            if ($registered === $uri) {
                return true;
            }
            if (RedirectUriPolicy::isLoopback($registered) && RedirectUriPolicy::isLoopback($uri)
                && RedirectUriPolicy::withoutPort($registered) === RedirectUriPolicy::withoutPort($uri)) {
                return true;
            }
        }

        return false;
    }

    /** Host of the first redirect URI, shown on the consent page. */
    public function redirectHost(?string $uri = null): string
    {
        return (string) (parse_url($uri ?? ($this->redirectUris[0] ?? ''), PHP_URL_HOST) ?? '');
    }
}
