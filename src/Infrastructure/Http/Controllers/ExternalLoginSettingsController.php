<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\TestLdapConnection;
use App\Application\Settings\Command\UpdateExternalLoginSettings;
use App\Application\Settings\Query\GetExternalLoginSettings;
use App\Domain\Auth\LdapDirectoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\User\Permission;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Origin;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Einstellungen › Anmeldung (LDAP/OAuth), migration 171. Administrators only. */
final class ExternalLoginSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly LdapDirectoryInterface $directory,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([], []);
    }

    public function update(Request $request): void
    {
        $text = static fn (string $key): string => $request->input($key, '') ?? '';
        $checked = static fn (string $key): bool => ($request->input($key, '') ?? '') === '1';

        try {
            $this->commands->dispatch(new UpdateExternalLoginSettings(
                loginMethod: $text('login_method'),
                ldapHost: $text('ldap_host'),
                ldapPort: $text('ldap_port'),
                ldapEncryption: $text('ldap_encryption'),
                ldapBindDn: $text('ldap_bind_dn'),
                ldapBindPassword: $text('ldap_bind_password'),
                ldapBaseDn: $text('ldap_base_dn'),
                ldapUserFilter: $text('ldap_user_filter'),
                ldapEmailAttribute: $text('ldap_email_attribute'),
                ldapFirstNameAttribute: $text('ldap_first_name_attribute'),
                ldapLastNameAttribute: $text('ldap_last_name_attribute'),
                oauthLabel: $text('oauth_label'),
                oauthClientId: $text('oauth_client_id'),
                oauthClientSecret: $text('oauth_client_secret'),
                oauthIssuerUrl: $text('oauth_issuer_url'),
                oauthAuthorizationUrl: $text('oauth_authorization_url'),
                oauthTokenUrl: $text('oauth_token_url'),
                oauthUserinfoUrl: $text('oauth_userinfo_url'),
                oauthScopes: $text('oauth_scopes'),
                oauthEmailClaim: $text('oauth_email_claim'),
                oauthFirstNameClaim: $text('oauth_first_name_claim'),
                oauthLastNameClaim: $text('oauth_last_name_claim'),
                oauthSkipTwoFactor: $checked('oauth_skip_two_factor'),
                autoCreateUsers: $checked('auto_create_users'),
                defaultPermissions: $request->inputArray('default_permissions'),
            ));
            Flash::success('Die Anmelde-Einstellungen wurden gespeichert.');
            Response::redirect('/einstellungen/anmeldung');
        } catch (ValidationException $e) {
            $this->render($e->errors(), $request->post);
        }
    }

    public function testLdap(Request $request): void
    {
        try {
            $this->commands->dispatch(new TestLdapConnection());
            Flash::success('Die Verbindung zum LDAP-Server funktioniert.');
        } catch (ValidationException $e) {
            Flash::error('LDAP-Test fehlgeschlagen: ' . implode(' ', $e->errors()));
        }

        Response::redirect('/einstellungen/anmeldung');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function render(array $errors, array $old): void
    {
        echo View::page('settings/external-login', [
            'settings' => $this->queries->ask(new GetExternalLoginSettings()),
            'errors' => $errors,
            'old' => $old,
            'ldapAvailable' => $this->directory->isAvailable(),
            'redirectUri' => Origin::current() . '/login/oauth/callback',
            'localLoginUrl' => Origin::current() . '/login/lokal',
            'permissions' => array_values(array_filter(
                Permission::all(),
                static fn (Permission $p): bool => $p !== Permission::Administration,
            )),
        ], activeNav: 'settings-anmeldung', title: 'Anmeldung (LDAP/OAuth)');
    }
}
