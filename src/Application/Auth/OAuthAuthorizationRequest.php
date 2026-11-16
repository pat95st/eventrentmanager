<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Settings\ExternalLoginSettings;

/**
 * First half of the OAuth login (authorization code flow with PKCE): the
 * URL the browser is sent to, plus the two secrets that stay in the session
 * until the identity provider redirects back.
 */
final class OAuthAuthorizationRequest
{
    private function __construct(
        public readonly string $url,
        public readonly string $state,
        public readonly string $codeVerifier,
    ) {
    }

    public static function create(ExternalLoginSettings $settings, string $redirectUri): self
    {
        $state = self::random();
        $codeVerifier = self::random();
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $endpoint = (string) $settings->oauthAuthorizationUrl;
        $url = $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query([
            'response_type' => 'code',
            'client_id' => (string) $settings->oauthClientId,
            'redirect_uri' => $redirectUri,
            'scope' => $settings->oauthScopes,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return new self($url, $state, $codeVerifier);
    }

    /** 43 URL-safe characters (256 bit), valid as PKCE code verifier. */
    private static function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
