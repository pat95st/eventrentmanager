<?php

declare(strict_types=1);

namespace Tests\Unit\Application\OAuth;

use App\Application\OAuth\OAuthError;
use App\Application\OAuth\OAuthService;
use App\Application\OAuth\OAuthTokenGuard;
use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use App\Domain\OAuth\OAuthScopePolicy;
use App\Domain\OAuth\RedirectUriPolicy;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryApiTokenRepository;
use Tests\Support\Fakes\InMemoryOAuthAuthorizationCodeRepository;
use Tests\Support\Fakes\InMemoryOAuthClientRepository;
use Tests\Support\Fakes\InMemoryOAuthGrantRepository;
use Tests\Support\Fakes\InMemoryUserRepository;

final class OAuthServiceTest extends Unit
{
    private const RESOURCE = 'https://verleih.test/mcp';
    private const ISSUER = 'https://verleih.test';
    private const REDIRECT = 'https://claude.ai/api/mcp/auth_callback';
    private const VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    private InMemoryOAuthClientRepository $clients;
    private InMemoryOAuthGrantRepository $grants;
    private InMemoryOAuthAuthorizationCodeRepository $codes;
    private InMemoryApiTokenRepository $tokens;
    private InMemoryUserRepository $users;
    private DateTimeImmutable $now;
    private OAuthService $service;

    protected function _before(): void
    {
        $this->clients = new InMemoryOAuthClientRepository();
        $this->tokens = new InMemoryApiTokenRepository();
        $this->grants = new InMemoryOAuthGrantRepository($this->tokens);
        $this->codes = new InMemoryOAuthAuthorizationCodeRepository();
        $this->users = new InMemoryUserRepository();
        $this->now = new DateTimeImmutable('2026-11-24 10:00:00');
        $this->service = new OAuthService($this->clients, $this->grants, $this->codes, $this->tokens, $this->users, fn () => $this->now);
        $this->users->save($this->user('admin', [Permission::Administration]));
        $this->users->save($this->user('sales', [Permission::Kundenverwaltung]));
        $this->users->save($this->user('tech', [Permission::Techniker]));
    }

    // --- Registration ------------------------------------------------------------

    public function testRegistersPublicAndConfidentialClients(): void
    {
        $public = $this->service->registerClient(['client_name' => '<b>Claude</b>', 'redirect_uris' => [self::REDIRECT]]);
        $this->assertSame('Claude', $public['client_name']);
        $this->assertSame('none', $public['token_endpoint_auth_method']);
        $this->assertArrayNotHasKey('client_secret', $public);

        $confidential = $this->service->registerClient(['redirect_uris' => ['http://127.0.0.1:8080/cb'], 'token_endpoint_auth_method' => 'client_secret_basic']);
        $this->assertSame('MCP-Client', $confidential['client_name']);
        $this->assertSame(64, strlen($confidential['client_secret']));
        $this->assertSame(hash('sha256', $confidential['client_secret']), $this->clients->findById($confidential['client_id'])?->clientSecretHash);
    }

    public function testRejectsUnsafeRegistrations(): void
    {
        foreach ([
            ['redirect_uris' => ['http://evil.example/cb']],
            ['redirect_uris' => ['https://ok.example/cb#frag']],
            ['redirect_uris' => []],
            ['redirect_uris' => [self::REDIRECT], 'grant_types' => ['client_credentials']],
            ['redirect_uris' => [self::REDIRECT], 'token_endpoint_auth_method' => 'private_key_jwt'],
        ] as $metadata) {
            try {
                $this->service->registerClient($metadata);
                $this->fail('rejected expected for ' . json_encode($metadata));
            } catch (OAuthError $e) {
                $this->assertContains($e->error, ['invalid_redirect_uri', 'invalid_client_metadata']);
            }
        }
    }

    public function testRedirectUriPolicy(): void
    {
        $this->assertNull(RedirectUriPolicy::validationError('https://claude.ai/cb'));
        $this->assertNull(RedirectUriPolicy::validationError('http://localhost:33418/callback'));
        $this->assertNull(RedirectUriPolicy::validationError('http://[::1]:5000/cb'));
        $this->assertNotNull(RedirectUriPolicy::validationError('http://192.168.1.2/cb'));
        $this->assertNotNull(RedirectUriPolicy::validationError('https://user:pw@claude.ai/cb'));
        $this->assertNotNull(RedirectUriPolicy::validationError('javascript:alert(1)'));
    }

    // --- Authorization request -------------------------------------------------

    public function testClientAndRedirectErrorsAreNotRedirected(): void
    {
        $clientId = $this->registerClient();

        $unknown = $this->authorizeError(['client_id' => 'nope'] + $this->authorizeQuery($clientId));
        $this->assertNull($unknown->redirectUrl(self::ISSUER));

        $foreign = $this->authorizeError(['redirect_uri' => 'https://evil.example/cb'] + $this->authorizeQuery($clientId));
        $this->assertNull($foreign->redirectUrl(self::ISSUER));
    }

    public function testOtherErrorsAreRedirectedWithState(): void
    {
        $clientId = $this->registerClient();

        $noPkce = $this->authorizeError(['code_challenge' => ''] + $this->authorizeQuery($clientId));
        $this->assertSame('invalid_request', $noPkce->error);
        $this->assertStringStartsWith(self::REDIRECT . '?error=invalid_request', (string) $noPkce->redirectUrl(self::ISSUER));
        $this->assertStringContainsString('state=abc', (string) $noPkce->redirectUrl(self::ISSUER));

        $this->assertSame('invalid_request', $this->authorizeError(['code_challenge_method' => 'plain'] + $this->authorizeQuery($clientId))->error);
        $this->assertSame('invalid_target', $this->authorizeError(['resource' => 'https://other.test/mcp'] + $this->authorizeQuery($clientId))->error);
        $this->assertSame('invalid_scope', $this->authorizeError(['scope' => 'everything'] + $this->authorizeQuery($clientId))->error);
        $this->assertSame('unsupported_response_type', $this->authorizeError(['response_type' => 'token'] + $this->authorizeQuery($clientId))->error);
    }

    public function testLoopbackRedirectAcceptsAnyPort(): void
    {
        $clientId = $this->service->registerClient(['redirect_uris' => ['http://127.0.0.1:1234/callback']])['client_id'];

        $request = $this->service->validateAuthorizationRequest(['redirect_uri' => 'http://127.0.0.1:55555/callback'] + $this->authorizeQuery($clientId), self::RESOURCE);
        $this->assertSame('http://127.0.0.1:55555/callback', $request->redirectUri);
        $this->authorizeError(['redirect_uri' => 'http://127.0.0.1:55555/other'] + $this->authorizeQuery($clientId));
    }

    public function testScopePolicyFollowsPermissions(): void
    {
        $admin = OAuthScopePolicy::allowedScopes($this->users->findById('admin'));
        $this->assertContains(ApiScope::ReportsRead, $admin);
        $this->assertNotContains(ApiScope::InvoicesFinalize, $admin);
        $this->assertNotContains(ApiScope::EmailSend, $admin);

        $this->assertSame([ApiScope::CustomersRead, ApiScope::CustomersWrite], OAuthScopePolicy::allowedScopes($this->users->findById('sales')));
        $this->assertSame([], OAuthScopePolicy::allowedScopes($this->users->findById('tech')), 'technicians only see their own events');
    }

    public function testOfferedScopesAreRequestedOnesTheUserHas(): void
    {
        $clientId = $this->registerClient();
        $request = $this->service->validateAuthorizationRequest(['scope' => 'customers:read invoices:read'] + $this->authorizeQuery($clientId), self::RESOURCE);

        $this->assertSame([ApiScope::CustomersRead], $this->service->offeredScopes($request, $this->users->findById('sales')));
        $this->assertSame([ApiScope::CustomersRead, ApiScope::InvoicesRead], $this->service->offeredScopes($request, $this->users->findById('admin')));
    }

    // --- Code exchange -----------------------------------------------------------

    public function testCodeExchangeIssuesAnMcpTokenForTheUser(): void
    {
        $clientId = $this->registerClient();
        $code = $this->approve($clientId, 'admin', ['customers:read', 'rentals:read', 'invoices:finalize']);

        $response = $this->exchange($clientId, $code);

        $this->assertSame('Bearer', $response['token_type']);
        $this->assertSame(3600, $response['expires_in']);
        $this->assertSame('customers:read rentals:read', $response['scope'], 'scopes the user cannot grant are dropped');
        $token = $this->tokens->findByTokenHash(hash('sha256', $response['access_token']));
        $this->assertNotNull($token);
        $this->assertTrue($token->mcpAllowed);
        $this->assertTrue($token->isOAuth());
        $this->assertSame('admin', $token->userId);
        $this->assertEquals($this->now->modify('+1 hour'), $token->expiresAt);
        $this->assertStringStartsWith('Claude (', $token->name);
        $this->assertCount(1, $this->grants->grants);
    }

    public function testCodeIsSingleUseAndBoundToClientVerifierAndRedirect(): void
    {
        $clientId = $this->registerClient();
        $otherClient = $this->registerClient();

        $code = $this->approve($clientId, 'admin', ['customers:read']);
        $this->assertTokenError('invalid_grant', fn () => $this->exchange($clientId, $code, verifier: str_repeat('a', 43)));
        $this->assertTokenError('invalid_grant', fn () => $this->exchange($clientId, $code), 'a failed attempt consumed the code');

        $code = $this->approve($clientId, 'admin', ['customers:read']);
        $this->assertTokenError('invalid_grant', fn () => $this->exchange($otherClient, $code));

        $code = $this->approve($clientId, 'admin', ['customers:read']);
        $this->assertTokenError('invalid_grant', fn () => $this->service->token(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $clientId, 'redirect_uri' => 'https://claude.ai/other', 'code_verifier' => self::VERIFIER], null, self::RESOURCE));

        $code = $this->approve($clientId, 'admin', ['customers:read']);
        $this->now = $this->now->modify('+11 minutes');
        $this->assertTokenError('invalid_grant', fn () => $this->exchange($clientId, $code), 'codes expire after 10 minutes');
    }

    public function testConfidentialClientsMustAuthenticate(): void
    {
        $registered = $this->service->registerClient(['redirect_uris' => [self::REDIRECT], 'token_endpoint_auth_method' => 'client_secret_basic']);
        $clientId = $registered['client_id'];

        $code = $this->approve($clientId, 'admin', ['customers:read']);
        try {
            $this->exchange($clientId, $code);
            $this->fail('invalid_client expected');
        } catch (OAuthError $e) {
            $this->assertSame('invalid_client', $e->error);
            $this->assertSame(401, $e->status);
        }

        $code = $this->approve($clientId, 'admin', ['customers:read']);
        $response = $this->service->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT, 'code_verifier' => self::VERIFIER], [$clientId, $registered['client_secret']], self::RESOURCE);
        $this->assertArrayHasKey('access_token', $response);
    }

    public function testReauthorizationReplacesTheConnection(): void
    {
        $clientId = $this->registerClient();
        $first = $this->exchange($clientId, $this->approve($clientId, 'admin', ['customers:read']));
        $this->exchange($clientId, $this->approve($clientId, 'admin', ['customers:read']));

        $this->assertCount(1, $this->grants->grants);
        $this->assertNull($this->tokens->findByTokenHash(hash('sha256', $first['access_token'])));
    }

    // --- Refresh -------------------------------------------------------------------

    public function testRefreshRotatesTokensAndDetectsReuse(): void
    {
        $clientId = $this->registerClient();
        $first = $this->exchange($clientId, $this->approve($clientId, 'admin', ['customers:read']));

        $second = $this->refresh($clientId, $first['refresh_token']);
        $this->assertNotSame($first['refresh_token'], $second['refresh_token']);
        $this->assertNull($this->tokens->findByTokenHash(hash('sha256', $first['access_token'])), 'old access token is replaced');
        $this->assertNotNull($this->tokens->findByTokenHash(hash('sha256', $second['access_token'])));

        $this->assertTokenError('invalid_grant', fn () => $this->refresh($clientId, $first['refresh_token']));
        $this->assertSame([], $this->grants->grants, 'reuse of a rotated refresh token revokes the connection');
        $this->assertNull($this->tokens->findByTokenHash(hash('sha256', $second['access_token'])));
    }

    public function testRefreshFollowsTheUsersCurrentPermissionsAndExpiry(): void
    {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->approve($clientId, 'admin', ['customers:read', 'reports:read']));

        // Administration withdrawn, customer management kept: reports:read disappears.
        $admin = $this->users->findById('admin');
        $admin->permissions = [Permission::Kundenverwaltung];
        $this->users->save($admin);
        $tokens = $this->refresh($clientId, $tokens['refresh_token']);
        $this->assertSame('customers:read', $tokens['scope']);

        // Deactivated: refresh fails and the connection is removed.
        $admin->isActive = false;
        $this->users->save($admin);
        $this->assertTokenError('invalid_grant', fn () => $this->refresh($clientId, $tokens['refresh_token']));
        $this->assertSame([], $this->grants->grants);

        // Expiry after 90 days.
        $admin->isActive = true;
        $admin->permissions = [Permission::Administration];
        $this->users->save($admin);
        $tokens = $this->exchange($clientId, $this->approve($clientId, 'admin', ['customers:read']));
        $this->now = $this->now->modify('+91 days');
        $this->assertTokenError('invalid_grant', fn () => $this->refresh($clientId, $tokens['refresh_token']));
    }

    // --- Revocation, connections, guard ----------------------------------------

    public function testRevocation(): void
    {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->approve($clientId, 'admin', ['customers:read']));

        $this->service->revoke($tokens['access_token']);
        $this->assertNull($this->tokens->findByTokenHash(hash('sha256', $tokens['access_token'])));
        $this->assertCount(1, $this->grants->grants, 'revoking the access token keeps the connection');

        $this->service->revoke($tokens['refresh_token']);
        $this->assertSame([], $this->grants->grants);
        $this->service->revoke('unknown'); // ignored
    }

    public function testUsersDisconnectOnlyTheirOwnConnections(): void
    {
        $clientId = $this->registerClient();
        $this->exchange($clientId, $this->approve($clientId, 'admin', ['customers:read']));
        $grantId = array_key_first($this->grants->grants);

        $connections = $this->service->connections('admin');
        $this->assertCount(1, $connections);
        $this->assertSame('Claude', $connections[0]->clientName);
        $this->assertSame('claude.ai', $connections[0]->redirectHost);
        $this->assertSame([], $this->service->connections('sales'));
        $this->assertCount(1, $this->service->connections(null));

        $this->assertFalse($this->service->disconnect($grantId, 'sales'));
        $this->assertTrue($this->service->disconnect($grantId, 'admin'));
        $this->assertSame([], $this->grants->grants);
    }

    public function testTokenGuard(): void
    {
        $guard = new OAuthTokenGuard($this->users);
        $plain = new ApiToken('t0', 'Shop', str_repeat('0', 64), '0000', null, true, null, $this->now, [ApiScope::ReportsRead]);
        $this->assertSame($plain, $guard($plain, $this->now), 'tokens from Settings › API pass unchanged');

        $oauth = new ApiToken('t1', 'Claude', str_repeat('1', 64), '1111', null, true, null, $this->now, [ApiScope::CustomersRead, ApiScope::ReportsRead], false, null, true, 'sales', 'g1', $this->now->modify('+1 hour'));
        $this->assertSame([ApiScope::CustomersRead], $guard($oauth, $this->now)?->scopes, 'narrowed to the owner\'s permissions');
        $this->assertNull($guard($oauth, $this->now->modify('+2 hours')), 'expired');

        $sales = $this->users->findById('sales');
        $sales->isActive = false;
        $this->users->save($sales);
        $this->assertNull($guard($oauth, $this->now), 'owner deactivated');
    }

    // --- helpers -------------------------------------------------------------------

    private function registerClient(): string
    {
        return $this->service->registerClient(['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT]])['client_id'];
    }

    /** @return array<string, string> */
    private function authorizeQuery(string $clientId): array
    {
        return [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'code_challenge' => OAuthService::s256(self::VERIFIER),
            'code_challenge_method' => 'S256',
            'state' => 'abc',
            'resource' => self::RESOURCE,
        ];
    }

    /** @param array<string, string> $query */
    private function authorizeError(array $query): OAuthError
    {
        try {
            $this->service->validateAuthorizationRequest($query, self::RESOURCE);
        } catch (OAuthError $e) {
            return $e;
        }
        $this->fail('OAuthError expected');
    }

    /** @param list<string> $scopes */
    private function approve(string $clientId, string $userId, array $scopes): string
    {
        $request = $this->service->validateAuthorizationRequest($this->authorizeQuery($clientId), self::RESOURCE);
        $url = $this->service->approve($request, $this->users->findById($userId), $scopes, self::ISSUER);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('abc', $query['state']);
        $this->assertSame(self::ISSUER, $query['iss']);

        return (string) $query['code'];
    }

    /** @return array<string, mixed> */
    private function exchange(string $clientId, string $code, string $verifier = self::VERIFIER): array
    {
        return $this->service->token(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT, 'code_verifier' => $verifier, 'resource' => self::RESOURCE], null, self::RESOURCE);
    }

    /** @return array<string, mixed> */
    private function refresh(string $clientId, string $refreshToken): array
    {
        return $this->service->token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken, 'client_id' => $clientId], null, self::RESOURCE);
    }

    private function assertTokenError(string $error, callable $call, string $message = ''): void
    {
        try {
            $call();
            $this->fail('OAuthError ' . $error . ' expected. ' . $message);
        } catch (OAuthError $e) {
            $this->assertSame($error, $e->error, $message);
        }
    }

    /** @param list<Permission> $permissions */
    private function user(string $id, array $permissions): User
    {
        return new User($id, $id . '@example.test', null, ucfirst($id), 'Test', $permissions, TwoFactorMethod::None, null, false, false, true, $this->now, $this->now);
    }
}
