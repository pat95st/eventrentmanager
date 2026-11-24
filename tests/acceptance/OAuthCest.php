<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * OAuth 2.1 for MCP clients end to end: registration, consent after the
 * normal login, code exchange with PKCE, calling /mcp with the access token,
 * refresh token rotation and reuse detection, revocation and disconnecting.
 */
final class OAuthCest
{
    private const REDIRECT = 'http://127.0.0.1:33418/callback';

    public function _before(AcceptanceTester $I): void
    {
        $I->updateInDatabase('api_settings', ['enabled' => 1, 'mcp_enabled' => 1], ['id' => 1]);
    }

    public function endpointsAre404WhileMcpIsOff(AcceptanceTester $I): void
    {
        $I->updateInDatabase('api_settings', ['mcp_enabled' => 0], ['id' => 1]);
        $I->amOnPage('/.well-known/oauth-authorization-server');
        $I->seeResponseCodeIs(404);
    }

    public function publishesMetadata(AcceptanceTester $I): void
    {
        $I->amOnPage('/.well-known/oauth-protected-resource');
        $I->seeResponseCodeIs(200);
        $resource = json_decode($I->grabPageSource(), true);
        $I->assertStringEndsWith('/mcp', $resource['resource']);

        $I->amOnPage('/.well-known/oauth-authorization-server');
        $server = json_decode($I->grabPageSource(), true);
        $I->assertSame(['S256'], $server['code_challenge_methods_supported']);
        $I->assertStringEndsWith('/oauth/register', $server['registration_endpoint']);

        [$status] = $I->sendMcpRequest(null, 'ping');
        $I->assertSame(401, $status);
        $I->assertStringContainsString('resource_metadata=', (string) $I->grabResponseHeader('WWW-Authenticate'));
    }

    public function authorizationRequiresLogin(AcceptanceTester $I): void
    {
        $clientId = $this->registerClient($I);
        $I->stopFollowingRedirects();
        $I->amOnPage('/oauth/authorize?' . http_build_query($this->authorizeParams($clientId, $this->challenge('v'))));
        $I->seeResponseCodeIs(303);
        $I->assertSame('/login', $I->grabResponseHeader('Location'));
    }

    public function fullFlowWithRefreshRotationAndRevocation(AcceptanceTester $I): void
    {
        $clientId = $this->registerClient($I);
        $verifier = str_repeat('verifier-', 6);
        $I->loginAsAdmin();

        // Consent page shows client, redirect host and the scopes.
        $I->amOnPage('/oauth/authorize?' . http_build_query($this->authorizeParams($clientId, $this->challenge($verifier)) + ['scope' => 'customers:read rentals:read']));
        $I->see('„Claude Test“ möchte auf den Materialverleih zugreifen');
        $I->see('127.0.0.1');
        $I->seeCheckboxIsChecked('input[value="customers:read"]');

        $I->stopFollowingRedirects();
        $I->submitForm('form[action="/oauth/authorize"]', ['decision' => 'allow', 'scopes' => ['customers:read']]);
        $location = $I->grabResponseHeader('Location');
        $I->assertStringStartsWith(self::REDIRECT . '?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $I->assertSame('xyz', $query['state']);
        $I->assertArrayHasKey('iss', $query);

        // Wrong verifier fails and burns nothing else; the right one works once.
        [$status, $error] = $I->sendFormRequest('/oauth/token', ['grant_type' => 'authorization_code', 'code' => $query['code'], 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT, 'code_verifier' => str_repeat('x', 43)]);
        $I->assertSame(400, $status);
        $I->assertSame('invalid_grant', $error['error']);
        $I->dontSeeInDatabase('oauth_grants', ['client_id' => $clientId]);

        $I->amOnPage('/oauth/authorize?' . http_build_query($this->authorizeParams($clientId, $this->challenge($verifier))));
        $I->submitForm('form[action="/oauth/authorize"]', ['decision' => 'allow', 'scopes' => ['customers:read', 'rentals:read']]);
        parse_str((string) parse_url($I->grabResponseHeader('Location'), PHP_URL_QUERY), $query);
        [$status, $tokens] = $I->sendFormRequest('/oauth/token', ['grant_type' => 'authorization_code', 'code' => $query['code'], 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT, 'code_verifier' => $verifier]);
        $I->assertSame(200, $status, (string) json_encode($tokens));
        $I->assertSame('Bearer', $tokens['token_type']);
        $I->assertSame(3600, $tokens['expires_in']);
        $I->assertSame('customers:read rentals:read', $tokens['scope']);
        [$status] = $I->sendFormRequest('/oauth/token', ['grant_type' => 'authorization_code', 'code' => $query['code'], 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT, 'code_verifier' => $verifier]);
        $I->assertSame(400, $status, 'codes are single use');

        // The access token works for /mcp (tools follow the granted scopes), not for the REST API.
        [$status, $body] = $I->sendMcpRequest($tokens['access_token'], 'tools/list');
        $I->assertSame(200, $status);
        $names = array_column($body['result']['tools'], 'name');
        $I->assertContains('search_customers', $names);
        $I->assertNotContains('create_customer', $names);
        [$status] = $I->sendApiRequest('GET', '/customers', $tokens['access_token']);
        $I->assertSame(401, $status);

        // Refresh rotates both tokens; the old access token is gone.
        [$status, $refreshed] = $I->sendFormRequest('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $clientId]);
        $I->assertSame(200, $status, (string) json_encode($refreshed));
        $I->assertNotSame($tokens['refresh_token'], $refreshed['refresh_token']);
        $I->assertSame(401, $I->sendMcpRequest($tokens['access_token'], 'ping')[0]);
        $I->assertSame(200, $I->sendMcpRequest($refreshed['access_token'], 'ping')[0]);

        // Reusing the rotated refresh token revokes the whole connection.
        [$status] = $I->sendFormRequest('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $clientId]);
        $I->assertSame(400, $status);
        $I->assertSame(401, $I->sendMcpRequest($refreshed['access_token'], 'ping')[0]);
        $I->dontSeeInDatabase('oauth_grants', ['client_id' => $clientId]);
    }

    public function userSeesAndDisconnectsTheConnection(AcceptanceTester $I): void
    {
        $clientId = $this->registerClient($I);
        $verifier = str_repeat('another-', 6);
        $I->loginAsAdmin();
        $I->stopFollowingRedirects();
        $I->amOnPage('/oauth/authorize?' . http_build_query($this->authorizeParams($clientId, $this->challenge($verifier))));
        $I->submitForm('form[action="/oauth/authorize"]', ['decision' => 'allow', 'scopes' => ['customers:read']]);
        parse_str((string) parse_url($I->grabResponseHeader('Location'), PHP_URL_QUERY), $query);
        [, $tokens] = $I->sendFormRequest('/oauth/token', ['grant_type' => 'authorization_code', 'code' => $query['code'], 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT, 'code_verifier' => $verifier]);
        $I->startFollowingRedirects();

        $I->amOnPage('/profil/ki-assistenten');
        $I->see('Claude Test');
        $I->amOnPage('/einstellungen/api');
        $I->see('Verbundene KI-Assistenten');
        $I->see('Claude Test');

        $I->amOnPage('/profil/ki-assistenten');
        $I->submitForm('form[action$="/trennen"]', []);
        $I->see('Die Verbindung wurde getrennt.');
        $I->assertSame(401, $I->sendMcpRequest($tokens['access_token'], 'ping')[0]);
    }

    public function denyingSendsAccessDeniedBack(AcceptanceTester $I): void
    {
        $clientId = $this->registerClient($I);
        $I->loginAsAdmin();
        $I->stopFollowingRedirects();
        $I->amOnPage('/oauth/authorize?' . http_build_query($this->authorizeParams($clientId, $this->challenge(str_repeat('d', 43)))));
        $I->submitForm('form[action="/oauth/authorize"]', ['decision' => 'deny']);
        $I->assertStringContainsString('error=access_denied', $I->grabResponseHeader('Location'));
        $I->assertStringContainsString('state=xyz', $I->grabResponseHeader('Location'));
    }

    private function registerClient(AcceptanceTester $I): string
    {
        [$status, $client] = $I->sendJsonPost('/oauth/register', ['client_name' => 'Claude Test', 'redirect_uris' => [self::REDIRECT]]);
        $I->assertSame(201, $status, (string) json_encode($client));

        return $client['client_id'];
    }

    /** @return array<string, string> */
    private function authorizeParams(string $clientId, string $challenge): array
    {
        return [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
        ];
    }

    private function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
