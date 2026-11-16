<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Auth;

use App\Application\Auth\Command\AuthenticateLdap;
use App\Application\Auth\Command\AuthenticateLdapHandler;
use App\Application\Auth\Command\CompleteOAuthLogin;
use App\Application\Auth\Command\CompleteOAuthLoginHandler;
use App\Application\Auth\ExternalUserProvisioner;
use App\Application\Auth\OAuthAuthorizationRequest;
use App\Domain\Auth\ExternalIdentity;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeLdapDirectory;
use Tests\Support\Fakes\FakeOAuthClient;
use Tests\Support\Fakes\InMemoryExternalLoginSettingsRepository;
use Tests\Support\Fakes\InMemoryUserRepository;

final class ExternalLoginTest extends Unit
{
    private InMemoryUserRepository $users;
    private InMemoryExternalLoginSettingsRepository $settings;
    private FakeLdapDirectory $directory;

    protected function _before(): void
    {
        // Loads the t() helper, which lives in the Translator's file.
        class_exists(\App\Infrastructure\Http\Translator::class);
        $this->users = new InMemoryUserRepository();
        $this->settings = new InMemoryExternalLoginSettingsRepository(new ExternalLoginSettings(
            ldapEnabled: true,
            ldapHost: 'ldap.example.org',
            ldapBaseDn: 'ou=people,dc=example,dc=org',
            oauthEnabled: true,
            oauthClientId: 'client',
            oauthAuthorizationUrl: 'https://idp.example.org/authorize',
            oauthTokenUrl: 'https://idp.example.org/token',
            oauthUserinfoUrl: 'https://idp.example.org/userinfo',
        ));
        $this->directory = new FakeLdapDirectory([
            'lena' => ['geheim', new ExternalIdentity('lena@example.org', 'Lena', 'Vogt')],
        ]);
    }

    public function testLdapLoginReturnsTheLocalUserWithTheSameEmailAddress(): void
    {
        $this->addUser('lena@example.org');

        $user = $this->ldap('lena', 'geheim');

        $this->assertSame('lena@example.org', $user->email);
    }

    public function testLdapLoginRejectsAWrongPassword(): void
    {
        $this->addUser('lena@example.org');

        $this->assertSame('E-Mail-Adresse oder Passwort ist falsch.', $this->ldapError('lena', 'falsch'));
    }

    public function testAnEmptyPasswordNeverReachesTheDirectory(): void
    {
        $this->ldapError('lena', '');

        $this->assertSame([], $this->directory->attempts);
    }

    public function testLdapIsNotAskedWhileItIsSwitchedOff(): void
    {
        $this->settings->settings->ldapEnabled = false;

        $this->ldapError('lena', 'geheim');

        $this->assertSame([], $this->directory->attempts);
    }

    public function testAnUnreachableDirectoryIsReportedAsSuch(): void
    {
        $this->directory->unreachable = true;

        $this->assertStringContainsString('LDAP', $this->ldapError('lena', 'geheim'));
    }

    public function testUnknownUsersAreRejectedUnlessAutoCreationIsOn(): void
    {
        $this->assertStringContainsString('kein Benutzerkonto', $this->ldapError('lena', 'geheim'));
        $this->assertNull($this->users->findByEmail('lena@example.org'));
    }

    public function testAutoCreationCreatesTheUserWithTheDefaultPermissionsButNeverAsAdministrator(): void
    {
        $this->settings->settings->autoCreateUsers = true;
        $this->settings->settings->defaultPermissions = [Permission::Kundenverwaltung, Permission::Administration];

        $user = $this->ldap('lena', 'geheim');

        $this->assertSame([Permission::Kundenverwaltung], $user->permissions);
        $this->assertSame('Lena Vogt', $user->displayName());
        $this->assertNull($user->passwordHash);
        $this->assertFalse($user->mustChangePassword);
        $this->assertNotNull($this->users->findByEmail('lena@example.org'));
    }

    public function testDeactivatedUsersCannotLogInExternally(): void
    {
        $this->addUser('lena@example.org', active: false);

        $this->assertStringContainsString('deaktiviert', $this->ldapError('lena', 'geheim'));
    }

    public function testOAuthLoginMapsTheEmailClaimToTheLocalUser(): void
    {
        $this->addUser('jonas@example.org');

        $user = $this->oauth(['email' => 'jonas@example.org', 'email_verified' => true]);

        $this->assertSame('jonas@example.org', $user->email);
    }

    public function testOAuthLoginRejectsAnInvalidCode(): void
    {
        $this->addUser('jonas@example.org');

        $this->expectException(ValidationException::class);
        $this->oauth(['email' => 'jonas@example.org'], code: 'forged');
    }

    public function testOAuthLoginRejectsAnUnverifiedEmailAddress(): void
    {
        $this->addUser('jonas@example.org');

        $this->expectException(ValidationException::class);
        $this->oauth(['email' => 'jonas@example.org', 'email_verified' => false]);
    }

    public function testOAuthLoginRejectsAResponseWithoutEmailAddress(): void
    {
        $this->expectException(ValidationException::class);
        $this->oauth(['sub' => '42']);
    }

    public function testOAuthLoginUsesTheConfiguredClaimNames(): void
    {
        $this->settings->settings->autoCreateUsers = true;
        $this->settings->settings->defaultPermissions = [Permission::Techniker];
        $this->settings->settings->oauthEmailClaim = 'upn';
        $this->settings->settings->oauthFirstNameClaim = 'first';

        $user = $this->oauth(['upn' => 'jonas@example.org', 'first' => 'Jonas', 'family_name' => 'Brandt']);

        $this->assertSame('Jonas Brandt', $user->displayName());
    }

    public function testAuthorizationUrlCarriesStateAndPkceChallenge(): void
    {
        $settings = $this->settings->get();
        $settings->oauthAuthorizationUrl = 'https://idp.example.org/authorize?tenant=a';

        $request = OAuthAuthorizationRequest::create($settings, 'https://app.example.org/login/oauth/callback');

        $this->assertStringStartsWith('https://idp.example.org/authorize?tenant=a&', $request->url);
        parse_str((string) parse_url($request->url, PHP_URL_QUERY), $query);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('client', $query['client_id']);
        $this->assertSame('https://app.example.org/login/oauth/callback', $query['redirect_uri']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame($request->state, $query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(
            rtrim(strtr(base64_encode(hash('sha256', $request->codeVerifier, true)), '+/', '-_'), '='),
            $query['code_challenge'],
        );
        $this->assertNotSame($request->state, $request->codeVerifier);
    }

    private function ldap(string $login, string $password): User
    {
        return (new AuthenticateLdapHandler($this->settings, $this->directory, new ExternalUserProvisioner($this->users)))
            ->handle(new AuthenticateLdap($login, $password));
    }

    private function ldapError(string $login, string $password): string
    {
        try {
            $this->ldap($login, $password);
        } catch (ValidationException $e) {
            return $e->errors()['credentials'];
        }

        $this->fail('Expected the login to be rejected.');
    }

    /** @param array<string, mixed> $claims */
    private function oauth(array $claims, string $code = FakeOAuthClient::VALID_CODE): User
    {
        return (new CompleteOAuthLoginHandler($this->settings, new FakeOAuthClient($claims), new ExternalUserProvisioner($this->users)))
            ->handle(new CompleteOAuthLogin($code, 'https://app.example.org/login/oauth/callback', 'verifier'));
    }

    private function addUser(string $email, bool $active = true): void
    {
        $now = new DateTimeImmutable();
        $this->users->save(new User(
            id: 'user-' . $email,
            email: $email,
            passwordHash: null,
            firstName: null,
            lastName: null,
            permissions: [Permission::Artikelverwaltung],
            twoFactorMethod: TwoFactorMethod::None,
            totpSecret: null,
            mustChangePassword: false,
            mustSetupTwoFactor: false,
            isActive: $active,
            createdAt: $now,
            updatedAt: $now,
        ));
    }
}
