<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Settings\ExternalLoginSettings;

interface OAuthClientInterface
{
    /**
     * Reads the OpenID Connect discovery document of an issuer.
     *
     * @return array{authorization: string, token: string, userinfo: string}
     * @throws ExternalLoginException
     */
    public function discover(string $issuerUrl): array;

    /**
     * Exchanges the authorization code for an access token and returns the
     * claims of the userinfo endpoint.
     *
     * @return array<string, mixed>
     * @throws ExternalLoginException
     */
    public function fetchClaims(ExternalLoginSettings $settings, string $code, string $redirectUri, string $codeVerifier): array;
}
