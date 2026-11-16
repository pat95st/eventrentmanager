<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\Settings\ExternalLoginSettingsRepositoryInterface;
use App\Domain\Settings\LdapEncryption;
use App\Domain\User\Permission;
use PDO;
use PDOException;

final class MariadbExternalLoginSettingsRepository implements ExternalLoginSettingsRepositoryInterface
{
    private const COLUMNS = [
        'ldap_enabled', 'ldap_host', 'ldap_port', 'ldap_encryption', 'ldap_bind_dn', 'ldap_bind_password',
        'ldap_base_dn', 'ldap_user_filter', 'ldap_email_attribute', 'ldap_first_name_attribute',
        'ldap_last_name_attribute', 'oauth_enabled', 'oauth_label', 'oauth_client_id', 'oauth_client_secret',
        'oauth_issuer_url', 'oauth_authorization_url', 'oauth_token_url', 'oauth_userinfo_url', 'oauth_scopes',
        'oauth_email_claim', 'oauth_first_name_claim', 'oauth_last_name_claim', 'oauth_skip_two_factor',
        'auto_create_users', 'default_permissions',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): ExternalLoginSettings
    {
        // Read on the (public) login page. If the table does not exist yet
        // (migration 171 not applied), external login is simply off instead
        // of breaking the login for everybody.
        try {
            $row = $this->pdo->query('SELECT * FROM external_login_settings WHERE id = 1')->fetch();
        } catch (PDOException) {
            return new ExternalLoginSettings();
        }
        if ($row === false) {
            return new ExternalLoginSettings();
        }

        $permissions = [];
        foreach (explode(',', (string) $row['default_permissions']) as $raw) {
            $permission = Permission::tryFrom($raw);
            if ($permission !== null) {
                $permissions[] = $permission;
            }
        }

        return new ExternalLoginSettings(
            ldapEnabled: (bool) $row['ldap_enabled'],
            ldapHost: $row['ldap_host'],
            ldapPort: (int) $row['ldap_port'],
            ldapEncryption: LdapEncryption::tryFrom((string) $row['ldap_encryption']) ?? LdapEncryption::None,
            ldapBindDn: $row['ldap_bind_dn'],
            ldapBindPassword: $row['ldap_bind_password'],
            ldapBaseDn: $row['ldap_base_dn'],
            ldapUserFilter: (string) $row['ldap_user_filter'],
            ldapEmailAttribute: (string) $row['ldap_email_attribute'],
            ldapFirstNameAttribute: (string) $row['ldap_first_name_attribute'],
            ldapLastNameAttribute: (string) $row['ldap_last_name_attribute'],
            oauthEnabled: (bool) $row['oauth_enabled'],
            oauthLabel: (string) $row['oauth_label'],
            oauthClientId: $row['oauth_client_id'],
            oauthClientSecret: $row['oauth_client_secret'],
            oauthIssuerUrl: $row['oauth_issuer_url'],
            oauthAuthorizationUrl: $row['oauth_authorization_url'],
            oauthTokenUrl: $row['oauth_token_url'],
            oauthUserinfoUrl: $row['oauth_userinfo_url'],
            oauthScopes: (string) $row['oauth_scopes'],
            oauthEmailClaim: (string) $row['oauth_email_claim'],
            oauthFirstNameClaim: (string) $row['oauth_first_name_claim'],
            oauthLastNameClaim: (string) $row['oauth_last_name_claim'],
            oauthSkipTwoFactor: (bool) $row['oauth_skip_two_factor'],
            autoCreateUsers: (bool) $row['auto_create_users'],
            defaultPermissions: $permissions,
        );
    }

    public function save(ExternalLoginSettings $settings): void
    {
        $assignments = implode(', ', array_map(static fn (string $c): string => "$c = VALUES($c)", self::COLUMNS));
        $placeholders = implode(', ', array_map(static fn (string $c): string => ':' . $c, self::COLUMNS));

        $this->pdo->prepare(
            'INSERT INTO external_login_settings (id, ' . implode(', ', self::COLUMNS) . ') VALUES (1, ' . $placeholders . ')
             ON DUPLICATE KEY UPDATE ' . $assignments
        )->execute([
            'ldap_enabled' => (int) $settings->ldapEnabled,
            'ldap_host' => $settings->ldapHost,
            'ldap_port' => $settings->ldapPort,
            'ldap_encryption' => $settings->ldapEncryption->value,
            'ldap_bind_dn' => $settings->ldapBindDn,
            'ldap_bind_password' => $settings->ldapBindPassword,
            'ldap_base_dn' => $settings->ldapBaseDn,
            'ldap_user_filter' => $settings->ldapUserFilter,
            'ldap_email_attribute' => $settings->ldapEmailAttribute,
            'ldap_first_name_attribute' => $settings->ldapFirstNameAttribute,
            'ldap_last_name_attribute' => $settings->ldapLastNameAttribute,
            'oauth_enabled' => (int) $settings->oauthEnabled,
            'oauth_label' => $settings->oauthLabel,
            'oauth_client_id' => $settings->oauthClientId,
            'oauth_client_secret' => $settings->oauthClientSecret,
            'oauth_issuer_url' => $settings->oauthIssuerUrl,
            'oauth_authorization_url' => $settings->oauthAuthorizationUrl,
            'oauth_token_url' => $settings->oauthTokenUrl,
            'oauth_userinfo_url' => $settings->oauthUserinfoUrl,
            'oauth_scopes' => $settings->oauthScopes,
            'oauth_email_claim' => $settings->oauthEmailClaim,
            'oauth_first_name_claim' => $settings->oauthFirstNameClaim,
            'oauth_last_name_claim' => $settings->oauthLastNameClaim,
            'oauth_skip_two_factor' => (int) $settings->oauthSkipTwoFactor,
            'auto_create_users' => (int) $settings->autoCreateUsers,
            'default_permissions' => implode(',', array_map(static fn (Permission $p): string => $p->value, $settings->defaultPermissions)),
        ]);
    }
}
