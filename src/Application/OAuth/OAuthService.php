<?php

declare(strict_types=1);

namespace App\Application\OAuth;

use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use App\Domain\Api\ApiTokenRepositoryInterface;
use App\Domain\OAuth\OAuthAuthorizationCode;
use App\Domain\OAuth\OAuthAuthorizationCodeRepositoryInterface;
use App\Domain\OAuth\OAuthClient;
use App\Domain\OAuth\OAuthClientRepositoryInterface;
use App\Domain\OAuth\OAuthGrant;
use App\Domain\OAuth\OAuthGrantRepositoryInterface;
use App\Domain\OAuth\OAuthScopePolicy;
use App\Domain\OAuth\RedirectUriPolicy;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\Uuid;
use Closure;
use DateTimeImmutable;

/**
 * OAuth 2.1 authorization server for the MCP server (/mcp), as required by
 * the MCP authorization specification:
 *
 * - dynamic client registration (RFC 7591), open: every access needs the
 *   user's consent; redirect URIs must be https or loopback http,
 * - authorization code flow with PKCE (S256 only), codes valid 10 minutes
 *   and single use, resource indicator (RFC 8707) bound to /mcp,
 * - access tokens (1 hour) are ApiTokens of the user's grant, released for
 *   MCP only; refresh tokens (90 days) rotate on every use, and reuse of an
 *   old refresh token revokes the whole grant,
 * - token revocation (RFC 7009) and disconnecting from the web interface.
 *
 * Scopes are limited to OAuthScopePolicy::allowedScopes() of the user.
 */
final class OAuthService
{
    public const ACCESS_TOKEN_TTL_SECONDS = 3600;
    public const REFRESH_TOKEN_TTL_DAYS = 90;
    public const CODE_TTL_SECONDS = 600;
    public const UNUSED_CLIENT_TTL_DAYS = 30;
    private const ACCESS_TOKEN_PREFIX = 'mvo_';
    private const REFRESH_TOKEN_PREFIX = 'mvr_';
    private const MAX_REDIRECT_URIS = 10;

    /** @param (Closure(): DateTimeImmutable)|null $clock */
    public function __construct(
        private readonly OAuthClientRepositoryInterface $clients,
        private readonly OAuthGrantRepositoryInterface $grants,
        private readonly OAuthAuthorizationCodeRepositoryInterface $codes,
        private readonly ApiTokenRepositoryInterface $tokens,
        private readonly UserRepositoryInterface $users,
        private readonly ?Closure $clock = null,
    ) {
    }

    // --- Dynamic client registration (RFC 7591) ---------------------------

    /**
     * @param array<string, mixed> $metadata client metadata from the request body
     * @return array<string, mixed> client information response (201)
     */
    public function registerClient(array $metadata): array
    {
        $now = $this->now();
        $uris = $metadata['redirect_uris'] ?? null;
        if (!is_array($uris) || $uris === [] || count($uris) > self::MAX_REDIRECT_URIS) {
            throw new OAuthError('invalid_redirect_uri', 'redirect_uris must be a list of 1 to ' . self::MAX_REDIRECT_URIS . ' URLs.');
        }
        $redirectUris = [];
        foreach ($uris as $uri) {
            $error = is_string($uri) ? RedirectUriPolicy::validationError($uri) : 'redirect_uris must contain strings.';
            if ($error !== null) {
                throw new OAuthError('invalid_redirect_uri', $error);
            }
            $redirectUris[] = $uri;
        }

        $method = $metadata['token_endpoint_auth_method'] ?? OAuthClient::AUTH_NONE;
        if (!in_array($method, [OAuthClient::AUTH_NONE, OAuthClient::AUTH_SECRET_POST, OAuthClient::AUTH_SECRET_BASIC], true)) {
            throw new OAuthError('invalid_client_metadata', 'token_endpoint_auth_method must be none, client_secret_post or client_secret_basic.');
        }
        foreach ((array) ($metadata['grant_types'] ?? ['authorization_code']) as $grantType) {
            if (!in_array($grantType, ['authorization_code', 'refresh_token'], true)) {
                throw new OAuthError('invalid_client_metadata', 'Only the grant types authorization_code and refresh_token are supported.');
            }
        }
        foreach ((array) ($metadata['response_types'] ?? ['code']) as $responseType) {
            if ($responseType !== 'code') {
                throw new OAuthError('invalid_client_metadata', 'Only the response type "code" is supported.');
            }
        }

        $name = is_string($metadata['client_name'] ?? null) ? trim(strip_tags($metadata['client_name'])) : '';
        $name = $name !== '' ? mb_substr($name, 0, 100) : 'MCP-Client';
        $secret = $method !== OAuthClient::AUTH_NONE ? bin2hex(random_bytes(32)) : null;
        $client = new OAuthClient(Uuid::v4(), $name, $redirectUris, $secret !== null ? hash('sha256', $secret) : null, $method, $now);
        $this->clients->save($client);
        $this->clients->purgeUnusedOlderThan($now->modify('-' . self::UNUSED_CLIENT_TTL_DAYS . ' days'));

        return array_filter([
            'client_id' => $client->id,
            'client_id_issued_at' => $now->getTimestamp(),
            'client_secret' => $secret,
            'client_secret_expires_at' => $secret !== null ? 0 : null,
            'client_name' => $client->name,
            'redirect_uris' => $client->redirectUris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $method,
        ], static fn ($v) => $v !== null);
    }

    // --- Authorization endpoint ----------------------------------------------

    /**
     * Validates GET /oauth/authorize. Errors about the client or redirect URI
     * are not redirectable (shown to the user); all others are.
     *
     * @param array<string, mixed> $query
     */
    public function validateAuthorizationRequest(array $query, string $resource): AuthorizationRequest
    {
        $clientId = is_string($query['client_id'] ?? null) ? $query['client_id'] : '';
        $client = $clientId !== '' ? $this->clients->findById($clientId) : null;
        if ($client === null) {
            throw new OAuthError('invalid_client', 'Unbekannter Client. Bitte die Verbindung im KI-Assistenten neu einrichten.');
        }
        $redirectUri = is_string($query['redirect_uri'] ?? null) ? $query['redirect_uri'] : null;
        if ($redirectUri === null && count($client->redirectUris) === 1) {
            $redirectUri = $client->redirectUris[0];
        }
        if ($redirectUri === null || !$client->allowsRedirectUri($redirectUri)) {
            throw new OAuthError('invalid_request', 'Die Rücksprungadresse (redirect_uri) ist für diesen Client nicht registriert.');
        }
        $state = is_string($query['state'] ?? null) ? $query['state'] : null;
        $fail = static fn (string $error, string $description) => new OAuthError($error, $description, 400, $redirectUri, $state);

        if (($query['response_type'] ?? null) !== 'code') {
            throw $fail('unsupported_response_type', 'Only response_type=code is supported.');
        }
        $challenge = is_string($query['code_challenge'] ?? null) ? $query['code_challenge'] : '';
        if (preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge) !== 1) {
            throw $fail('invalid_request', 'PKCE is required: send code_challenge (S256).');
        }
        if (($query['code_challenge_method'] ?? null) !== 'S256') {
            throw $fail('invalid_request', 'code_challenge_method must be S256.');
        }
        $requested = null;
        if (is_string($query['scope'] ?? null) && trim($query['scope']) !== '') {
            $requested = ApiScope::parseList(preg_split('/\s+/', trim($query['scope'])) ?: []);
            if ($requested === []) {
                throw $fail('invalid_scope', 'None of the requested scopes is known.');
            }
        }
        $requestedResource = is_string($query['resource'] ?? null) && $query['resource'] !== '' ? $query['resource'] : null;
        if ($requestedResource !== null && !self::sameResource($requestedResource, $resource)) {
            throw $fail('invalid_target', 'This server only issues tokens for ' . $resource . '.');
        }

        return new AuthorizationRequest($client, $redirectUri, $state, $challenge, $requested, $requestedResource);
    }

    /** Scopes to offer on the consent page: requested ∩ allowed (all allowed when nothing was requested). @return list<ApiScope> */
    public function offeredScopes(AuthorizationRequest $request, User $user): array
    {
        $allowed = OAuthScopePolicy::allowedScopes($user);
        if ($request->requestedScopes === null) {
            return $allowed;
        }

        return array_values(array_filter($allowed, static fn (ApiScope $s) => in_array($s, $request->requestedScopes, true)));
    }

    /**
     * The user consented: issues an authorization code for the chosen scopes
     * and returns the redirect URL back to the client.
     *
     * @param list<string> $chosenScopes scope values ticked on the consent page
     */
    public function approve(AuthorizationRequest $request, User $user, array $chosenScopes, string $issuer): string
    {
        $offered = $this->offeredScopes($request, $user);
        $scopes = array_values(array_filter($offered, static fn (ApiScope $s) => in_array($s->value, $chosenScopes, true)));
        if ($scopes === []) {
            throw new OAuthError('invalid_scope', 'Bitte mindestens eine Berechtigung auswählen.');
        }
        $now = $this->now();
        $code = bin2hex(random_bytes(32));
        $this->codes->save(new OAuthAuthorizationCode(
            codeHash: hash('sha256', $code),
            clientId: $request->client->id,
            userId: $user->id,
            redirectUri: $request->redirectUri,
            codeChallenge: $request->codeChallenge,
            scopes: $scopes,
            resource: $request->resource,
            expiresAt: $now->modify('+' . self::CODE_TTL_SECONDS . ' seconds'),
            createdAt: $now,
        ));

        return $request->redirectUrl(['code' => $code, 'iss' => $issuer]);
    }

    public function deny(AuthorizationRequest $request, string $issuer): string
    {
        return $request->redirectUrl(['error' => 'access_denied', 'error_description' => 'The user denied access.', 'iss' => $issuer]);
    }

    // --- Token endpoint --------------------------------------------------------

    /**
     * POST /oauth/token.
     *
     * @param array<string, mixed> $params form parameters
     * @param array{0: string, 1: string}|null $basicAuth client id and secret from "Authorization: Basic"
     * @return array<string, mixed> token response
     */
    public function token(array $params, ?array $basicAuth, string $resource): array
    {
        $grantType = $params['grant_type'] ?? null;
        $client = $this->authenticateClient($params, $basicAuth);
        $requestedResource = is_string($params['resource'] ?? null) && $params['resource'] !== '' ? $params['resource'] : null;
        if ($requestedResource !== null && !self::sameResource($requestedResource, $resource)) {
            throw new OAuthError('invalid_target', 'This server only issues tokens for ' . $resource . '.');
        }

        $response = match ($grantType) {
            'authorization_code' => $this->exchangeCode($client, $params),
            'refresh_token' => $this->refresh($client, $params),
            default => throw new OAuthError('unsupported_grant_type', 'Supported grant types: authorization_code, refresh_token.'),
        };
        $this->clients->touchLastUsed($client->id, $this->now());

        return $response;
    }

    /** @param array<string, mixed> $params */
    private function exchangeCode(OAuthClient $client, array $params): array
    {
        $now = $this->now();
        $this->codes->purgeExpired($now);
        $raw = is_string($params['code'] ?? null) ? $params['code'] : '';
        $code = $raw !== '' ? $this->codes->consume(hash('sha256', $raw)) : null;
        if ($code === null || $code->clientId !== $client->id || $code->expiresAt < $now) {
            throw new OAuthError('invalid_grant', 'The authorization code is invalid, expired or already used.');
        }
        if (($params['redirect_uri'] ?? $code->redirectUri) !== $code->redirectUri) {
            throw new OAuthError('invalid_grant', 'redirect_uri does not match the authorization request.');
        }
        $verifier = is_string($params['code_verifier'] ?? null) ? $params['code_verifier'] : '';
        if (preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier) !== 1 || !hash_equals($code->codeChallenge, self::s256($verifier))) {
            throw new OAuthError('invalid_grant', 'PKCE verification failed (code_verifier).');
        }
        $user = $this->users->findById($code->userId);
        $scopes = $user !== null ? self::intersect($code->scopes, OAuthScopePolicy::allowedScopes($user)) : [];
        if ($user === null || $scopes === []) {
            throw new OAuthError('invalid_grant', 'The user can no longer grant access.');
        }

        // A new authorization replaces earlier connections of the same client and user.
        foreach ($this->grants->findByClientAndUser($client->id, $user->id) as $old) {
            $this->grants->delete($old->id);
        }
        $refresh = self::REFRESH_TOKEN_PREFIX . bin2hex(random_bytes(32));
        $grant = new OAuthGrant(
            id: Uuid::v4(),
            clientId: $client->id,
            userId: $user->id,
            scopes: $scopes,
            refreshTokenHash: hash('sha256', $refresh),
            previousRefreshTokenHash: null,
            refreshExpiresAt: $now->modify('+' . self::REFRESH_TOKEN_TTL_DAYS . ' days'),
            createdAt: $now,
            lastUsedAt: $now,
        );
        $this->grants->save($grant);

        return $this->issueAccessToken($client, $user, $grant, $refresh, $now);
    }

    /** @param array<string, mixed> $params */
    private function refresh(OAuthClient $client, array $params): array
    {
        $now = $this->now();
        $raw = is_string($params['refresh_token'] ?? null) ? $params['refresh_token'] : '';
        $hash = hash('sha256', $raw);
        $grant = $raw !== '' ? $this->grants->findByRefreshTokenHash($hash) : null;
        if ($grant === null) {
            // A rotated refresh token was used again: it may have been stolen — revoke the connection.
            $reused = $raw !== '' ? $this->grants->findByPreviousRefreshTokenHash($hash) : null;
            if ($reused !== null) {
                $this->grants->delete($reused->id);
            }
            throw new OAuthError('invalid_grant', 'The refresh token is invalid or was already used.');
        }
        if ($grant->clientId !== $client->id) {
            throw new OAuthError('invalid_grant', 'The refresh token was issued to another client.');
        }
        if ($grant->refreshExpiresAt < $now) {
            $this->grants->delete($grant->id);
            throw new OAuthError('invalid_grant', 'The refresh token has expired. Please connect again.');
        }
        $user = $this->users->findById($grant->userId);
        $scopes = $user !== null ? self::intersect($grant->scopes, OAuthScopePolicy::allowedScopes($user)) : [];
        if (is_string($params['scope'] ?? null) && trim($params['scope']) !== '') {
            $scopes = self::intersect($scopes, ApiScope::parseList(preg_split('/\s+/', trim($params['scope'])) ?: []));
        }
        if ($user === null || $scopes === []) {
            $this->grants->delete($grant->id);
            throw new OAuthError('invalid_grant', 'The user can no longer grant access.');
        }

        $refresh = self::REFRESH_TOKEN_PREFIX . bin2hex(random_bytes(32));
        $grant->previousRefreshTokenHash = $grant->refreshTokenHash;
        $grant->refreshTokenHash = hash('sha256', $refresh);
        $grant->refreshExpiresAt = $now->modify('+' . self::REFRESH_TOKEN_TTL_DAYS . ' days');
        $grant->scopes = $scopes;
        $grant->lastUsedAt = $now;
        $this->grants->save($grant);

        return $this->issueAccessToken($client, $user, $grant, $refresh, $now);
    }

    /** @return array<string, mixed> */
    private function issueAccessToken(OAuthClient $client, User $user, OAuthGrant $grant, string $refresh, DateTimeImmutable $now): array
    {
        $this->tokens->deleteByOAuthGrant($grant->id);
        $access = self::ACCESS_TOKEN_PREFIX . bin2hex(random_bytes(32));
        $this->tokens->save(new ApiToken(
            id: Uuid::v4(),
            name: mb_substr($client->name . ' (' . $user->displayName() . ')', 0, 255),
            tokenHash: hash('sha256', $access),
            tokenLastFour: substr($access, -4),
            ipAllowlist: null,
            enabled: true,
            lastUsedAt: null,
            createdAt: $now,
            scopes: $grant->scopes,
            readOnly: false,
            rateLimitPerMinute: null,
            mcpAllowed: true,
            userId: $user->id,
            oauthGrantId: $grant->id,
            expiresAt: $now->modify('+' . self::ACCESS_TOKEN_TTL_SECONDS . ' seconds'),
        ));

        return [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
            'refresh_token' => $refresh,
            'scope' => implode(' ', $grant->scopeValues()),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @param array{0: string, 1: string}|null $basicAuth
     */
    private function authenticateClient(array $params, ?array $basicAuth): OAuthClient
    {
        $clientId = $basicAuth[0] ?? (is_string($params['client_id'] ?? null) ? $params['client_id'] : '');
        $secret = $basicAuth[1] ?? (is_string($params['client_secret'] ?? null) ? $params['client_secret'] : null);
        $client = $clientId !== '' ? $this->clients->findById($clientId) : null;
        if ($client === null) {
            throw new OAuthError('invalid_client', 'Unknown client.', 401);
        }
        if ($client->isConfidential() && ($secret === null || $client->clientSecretHash === null || !hash_equals($client->clientSecretHash, hash('sha256', $secret)))) {
            throw new OAuthError('invalid_client', 'Client authentication failed.', 401);
        }

        return $client;
    }

    // --- Revocation and connections ---------------------------------------------

    /** RFC 7009: revokes an access token, or a refresh token together with its grant. Unknown tokens are ignored. */
    public function revoke(string $token): void
    {
        if ($token === '') {
            return;
        }
        $hash = hash('sha256', $token);
        $grant = $this->grants->findByRefreshTokenHash($hash);
        if ($grant !== null) {
            $this->grants->delete($grant->id);

            return;
        }
        $accessToken = $this->tokens->findByTokenHash($hash);
        if ($accessToken !== null && $accessToken->isOAuth()) {
            $this->tokens->delete($accessToken->id);
        }
    }

    /** Disconnects a client; $userId restricts it to the user's own connections (null = administrator). */
    public function disconnect(string $grantId, ?string $userId): bool
    {
        $grant = $this->grants->findById($grantId);
        if ($grant === null || ($userId !== null && $grant->userId !== $userId)) {
            return false;
        }
        $this->grants->delete($grant->id);

        return true;
    }

    /** @return list<OAuthConnection> */
    public function connections(?string $userId): array
    {
        $grants = $userId !== null ? $this->grants->findByUser($userId) : $this->grants->findAll();
        $lastUse = [];
        foreach ($this->tokens->findAll() as $token) {
            if ($token->oauthGrantId !== null && $token->lastUsedAt !== null) {
                $lastUse[$token->oauthGrantId] = $token->lastUsedAt;
            }
        }
        $connections = [];
        foreach ($grants as $grant) {
            $client = $this->clients->findById($grant->clientId);
            $user = $this->users->findById($grant->userId);
            $used = $lastUse[$grant->id] ?? null;
            if ($grant->lastUsedAt !== null && ($used === null || $grant->lastUsedAt > $used)) {
                $used = $grant->lastUsedAt;
            }
            $connections[] = new OAuthConnection(
                $grant,
                $client?->name ?? '?',
                $client?->redirectHost() ?? '',
                $user?->displayName() ?? '?',
                $used,
            );
        }

        return $connections;
    }

    public function findClient(string $id): ?OAuthClient
    {
        return $this->clients->findById($id);
    }

    // --- Helpers -----------------------------------------------------------------

    public static function s256(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private static function sameResource(string $a, string $b): bool
    {
        return rtrim($a, '/') === rtrim($b, '/');
    }

    /**
     * @param list<ApiScope> $scopes
     * @param list<ApiScope> $allowed
     * @return list<ApiScope>
     */
    private static function intersect(array $scopes, array $allowed): array
    {
        return array_values(array_filter($scopes, static fn (ApiScope $s) => in_array($s, $allowed, true)));
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
