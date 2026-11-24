<?php

declare(strict_types=1);

namespace App\Application\OAuth;

use App\Domain\Api\ApiScope;
use App\Domain\OAuth\OAuthClient;

/** Validated parameters of GET /oauth/authorize, carried through the consent page. */
final class AuthorizationRequest
{
    /** @param list<ApiScope>|null $requestedScopes null = the client asked for no particular scopes */
    public function __construct(
        public readonly OAuthClient $client,
        public readonly string $redirectUri,
        public readonly ?string $state,
        public readonly string $codeChallenge,
        public readonly ?array $requestedScopes,
        public readonly ?string $resource,
    ) {
    }

    /** Query parameters to repeat the request (hidden fields of the consent form). @return array<string, string> */
    public function parameters(): array
    {
        return array_filter([
            'response_type' => 'code',
            'client_id' => $this->client->id,
            'redirect_uri' => $this->redirectUri,
            'state' => $this->state,
            'code_challenge' => $this->codeChallenge,
            'code_challenge_method' => 'S256',
            'scope' => $this->requestedScopes !== null ? implode(' ', array_map(static fn (ApiScope $s) => $s->value, $this->requestedScopes)) : null,
            'resource' => $this->resource,
        ], static fn ($v) => $v !== null);
    }

    /** Redirect back to the client with the given parameters (plus state). @param array<string, string> $params */
    public function redirectUrl(array $params): string
    {
        return self::buildRedirectUrl($this->redirectUri, $this->state, $params);
    }

    /** @param array<string, string> $params */
    public static function buildRedirectUrl(string $redirectUri, ?string $state, array $params): string
    {
        if ($state !== null) {
            $params['state'] = $state;
        }

        return $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
