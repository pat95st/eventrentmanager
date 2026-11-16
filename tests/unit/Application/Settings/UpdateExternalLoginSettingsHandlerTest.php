<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Settings;

use App\Application\Settings\Command\UpdateExternalLoginSettings;
use App\Application\Settings\Command\UpdateExternalLoginSettingsHandler;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\Settings\LdapEncryption;
use App\Domain\User\Permission;
use Codeception\Test\Unit;
use Tests\Support\Fakes\FakeLdapDirectory;
use Tests\Support\Fakes\FakeOAuthClient;
use Tests\Support\Fakes\InMemoryExternalLoginSettingsRepository;

final class UpdateExternalLoginSettingsHandlerTest extends Unit
{
    private InMemoryExternalLoginSettingsRepository $repository;
    private FakeLdapDirectory $directory;
    private FakeOAuthClient $oauthClient;

    protected function _before(): void
    {
        $this->repository = new InMemoryExternalLoginSettingsRepository();
        $this->directory = new FakeLdapDirectory();
        $this->oauthClient = new FakeOAuthClient();
    }

    public function testStoresLdapSettingsWithDefaultsForEmptyFields(): void
    {
        $this->handle(new UpdateExternalLoginSettings(
            ldapEnabled: true,
            ldapHost: ' ldap.example.org ',
            ldapEncryption: 'ldaps',
            ldapBindDn: 'cn=reader,dc=example,dc=org',
            ldapBindPassword: 'geheim',
            ldapBaseDn: 'dc=example,dc=org',
        ));

        $saved = $this->repository->get();
        $this->assertTrue($saved->ldapActive());
        $this->assertSame('ldap.example.org', $saved->ldapHost);
        $this->assertSame(LdapEncryption::Ldaps, $saved->ldapEncryption);
        $this->assertSame(636, $saved->ldapPort);
        $this->assertSame(ExternalLoginSettings::DEFAULT_LDAP_USER_FILTER, $saved->ldapUserFilter);
        $this->assertSame('mail', $saved->ldapEmailAttribute);
        $this->assertSame('geheim', $saved->ldapBindPassword);
        $this->assertFalse($saved->oauthActive());
    }

    public function testEmptySecretsKeepTheStoredValues(): void
    {
        $this->repository->settings = new ExternalLoginSettings(ldapBindPassword: 'alt', oauthClientSecret: 'secret');

        $this->handle(new UpdateExternalLoginSettings(ldapBindDn: 'cn=reader,dc=example,dc=org'));

        $this->assertSame('alt', $this->repository->get()->ldapBindPassword);
        $this->assertSame('secret', $this->repository->get()->oauthClientSecret);
    }

    public function testRemovingTheBindDnAlsoRemovesItsPassword(): void
    {
        $this->repository->settings = new ExternalLoginSettings(ldapBindDn: 'cn=reader', ldapBindPassword: 'alt');

        $this->handle(new UpdateExternalLoginSettings());

        $this->assertNull($this->repository->get()->ldapBindPassword);
    }

    public function testEnablingLdapRequiresServerBaseDnAndThePhpExtension(): void
    {
        $this->directory->available = false;

        $errors = $this->errors(new UpdateExternalLoginSettings(ldapEnabled: true));

        $this->assertArrayHasKey('ldap_enabled', $errors);
        $this->assertArrayHasKey('ldap_host', $errors);
        $this->assertArrayHasKey('ldap_base_dn', $errors);
    }

    public function testRejectsAHostWithSchemeAndAFilterWithoutPlaceholder(): void
    {
        $errors = $this->errors(new UpdateExternalLoginSettings(ldapHost: 'ldap://ldap.example.org', ldapUserFilter: '(uid=lena)'));

        $this->assertArrayHasKey('ldap_host', $errors);
        $this->assertArrayHasKey('ldap_user_filter', $errors);
    }

    public function testDiscoversMissingOAuthEndpointsFromTheIssuer(): void
    {
        $this->handle(new UpdateExternalLoginSettings(
            oauthEnabled: true,
            oauthClientId: 'client',
            oauthIssuerUrl: 'https://idp.example.org',
            oauthTokenUrl: 'https://other.example.org/token',
        ));

        $saved = $this->repository->get();
        $this->assertTrue($saved->oauthActive());
        $this->assertSame('https://idp.example.org/authorize', $saved->oauthAuthorizationUrl);
        $this->assertSame('https://other.example.org/token', $saved->oauthTokenUrl);
        $this->assertSame('https://idp.example.org/userinfo', $saved->oauthUserinfoUrl);
        $this->assertSame(ExternalLoginSettings::DEFAULT_OAUTH_LABEL, $saved->oauthLabel);
    }

    public function testDoesNotAskTheIssuerWhenAllEndpointsAreGiven(): void
    {
        $this->handle(new UpdateExternalLoginSettings(
            oauthIssuerUrl: 'https://idp.example.org',
            oauthAuthorizationUrl: 'https://idp.example.org/a',
            oauthTokenUrl: 'https://idp.example.org/t',
            oauthUserinfoUrl: 'https://idp.example.org/u',
        ));

        $this->assertSame([], $this->oauthClient->discovered);
    }

    public function testReportsAFailedDiscoveryAtTheIssuerField(): void
    {
        $this->oauthClient->discoveryFails = true;

        $errors = $this->errors(new UpdateExternalLoginSettings(oauthEnabled: true, oauthClientId: 'client', oauthIssuerUrl: 'https://idp.example.org'));

        $this->assertSame(['oauth_issuer_url'], array_keys($errors));
    }

    public function testEnablingOAuthRequiresClientIdAndEndpoints(): void
    {
        $errors = $this->errors(new UpdateExternalLoginSettings(oauthEnabled: true, oauthTokenUrl: 'javascript:alert(1)'));

        $this->assertArrayHasKey('oauth_client_id', $errors);
        $this->assertArrayHasKey('oauth_authorization_url', $errors);
        $this->assertArrayHasKey('oauth_token_url', $errors);
        $this->assertArrayHasKey('oauth_userinfo_url', $errors);
    }

    public function testAutoCreationNeedsPermissionsAndNeverGrantsAdministration(): void
    {
        $this->assertArrayHasKey('default_permissions', $this->errors(new UpdateExternalLoginSettings(autoCreateUsers: true)));
        $this->assertArrayHasKey('default_permissions', $this->errors(new UpdateExternalLoginSettings(
            autoCreateUsers: true,
            defaultPermissions: ['administration'],
        )));

        $this->handle(new UpdateExternalLoginSettings(autoCreateUsers: true, defaultPermissions: ['techniker', 'techniker']));

        $this->assertSame([Permission::Techniker], $this->repository->get()->defaultPermissions);
    }

    private function handle(UpdateExternalLoginSettings $command): void
    {
        (new UpdateExternalLoginSettingsHandler($this->repository, $this->directory, $this->oauthClient))->handle($command);
    }

    /** @return array<string, string> */
    private function errors(UpdateExternalLoginSettings $command): array
    {
        try {
            $this->handle($command);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Expected a validation error.');
    }
}
