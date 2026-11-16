<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\ExternalLoginException;
use App\Domain\Auth\LdapDirectoryInterface;
use App\Domain\Auth\OAuthClientInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\Settings\ExternalLoginSettingsRepositoryInterface;
use App\Domain\Settings\LdapEncryption;
use App\Domain\User\Permission;

final class UpdateExternalLoginSettingsHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ExternalLoginSettingsRepositoryInterface $settings,
        private readonly LdapDirectoryInterface $directory,
        private readonly OAuthClientInterface $oauthClient,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateExternalLoginSettings);

        $current = $this->settings->get();
        $errors = [];

        // --- LDAP ---------------------------------------------------------
        $encryption = LdapEncryption::tryFrom($command->ldapEncryption);
        if ($encryption === null) {
            $errors['ldap_encryption'] = 'Ungültige Verschlüsselung ausgewählt.';
            $encryption = LdapEncryption::None;
        }

        $host = trim($command->ldapHost);
        if ($host !== '' && preg_match('/^(?:[A-Za-z0-9._-]+|\[[0-9A-Fa-f:]+\])$/', $host) !== 1) {
            $errors['ldap_host'] = 'Bitte nur den Hostnamen oder die IP-Adresse angeben (ohne ldap:// und ohne Port).';
        } elseif (strlen($host) > 255) {
            $errors['ldap_host'] = 'Der Hostname ist zu lang.';
        }

        $portRaw = trim($command->ldapPort);
        $port = $encryption->defaultPort();
        if ($portRaw !== '') {
            if (preg_match('/^\d{1,5}$/', $portRaw) !== 1 || (int) $portRaw < 1 || (int) $portRaw > 65535) {
                $errors['ldap_port'] = 'Bitte einen Port zwischen 1 und 65535 angeben.';
            } else {
                $port = (int) $portRaw;
            }
        }

        $bindDn = self::limited($command->ldapBindDn, 255, 'ldap_bind_dn', $errors);
        $baseDn = self::limited($command->ldapBaseDn, 255, 'ldap_base_dn', $errors);

        $bindPassword = null;
        if ($bindDn !== '') {
            $bindPassword = $command->ldapBindPassword !== '' ? $command->ldapBindPassword : $current->ldapBindPassword;
            if ($bindPassword !== null && strlen($bindPassword) > 255) {
                $errors['ldap_bind_password'] = 'Das Passwort ist zu lang (höchstens 255 Zeichen).';
            }
        }

        $filter = trim($command->ldapUserFilter);
        if ($filter === '') {
            $filter = ExternalLoginSettings::DEFAULT_LDAP_USER_FILTER;
        }
        if (
            strlen($filter) > 500
            || !str_contains($filter, '%s')
            || !str_starts_with($filter, '(')
            || !str_ends_with($filter, ')')
            || substr_count($filter, '(') !== substr_count($filter, ')')
        ) {
            $errors['ldap_user_filter'] = 'Der Filter muss in Klammern stehen und den Platzhalter %s für den Anmeldenamen enthalten, z. B. (uid=%s).';
        }

        $emailAttribute = self::identifier($command->ldapEmailAttribute, 'mail', '/^[A-Za-z][A-Za-z0-9-]*$/', 'ldap_email_attribute', $errors);
        $firstNameAttribute = self::identifier($command->ldapFirstNameAttribute, 'givenName', '/^[A-Za-z][A-Za-z0-9-]*$/', 'ldap_first_name_attribute', $errors);
        $lastNameAttribute = self::identifier($command->ldapLastNameAttribute, 'sn', '/^[A-Za-z][A-Za-z0-9-]*$/', 'ldap_last_name_attribute', $errors);

        if ($command->ldapEnabled) {
            if (!$this->directory->isAvailable()) {
                $errors['ldap_enabled'] = 'Auf diesem Server fehlt die PHP-Erweiterung „ldap“. Bitte zuerst installieren (z. B. Paket php-ldap).';
            }
            if ($host === '') {
                $errors['ldap_host'] = 'Bitte den LDAP-Server angeben.';
            }
            if ($baseDn === '') {
                $errors['ldap_base_dn'] = 'Bitte die Basis-DN angeben, unter der die Benutzer gesucht werden.';
            }
        }

        // --- OAuth 2.0 / OpenID Connect -----------------------------------
        $label = trim($command->oauthLabel);
        if ($label === '') {
            $label = ExternalLoginSettings::DEFAULT_OAUTH_LABEL;
        } elseif (mb_strlen($label) > 100) {
            $errors['oauth_label'] = 'Die Beschriftung ist zu lang (höchstens 100 Zeichen).';
        }

        $clientId = self::limited($command->oauthClientId, 255, 'oauth_client_id', $errors);
        $clientSecret = $command->oauthClientSecret !== '' ? $command->oauthClientSecret : $current->oauthClientSecret;
        if ($clientSecret !== null && strlen($clientSecret) > 2000) {
            $errors['oauth_client_secret'] = 'Das Client-Secret ist zu lang.';
        }

        $issuerUrl = self::url($command->oauthIssuerUrl, 'oauth_issuer_url', $errors);
        $authorizationUrl = self::url($command->oauthAuthorizationUrl, 'oauth_authorization_url', $errors);
        $tokenUrl = self::url($command->oauthTokenUrl, 'oauth_token_url', $errors);
        $userinfoUrl = self::url($command->oauthUserinfoUrl, 'oauth_userinfo_url', $errors);

        // Endpoints left empty are filled from the discovery document.
        if (
            $issuerUrl !== null
            && !isset($errors['oauth_issuer_url'])
            && ($authorizationUrl === null || $tokenUrl === null || $userinfoUrl === null)
            && !isset($errors['oauth_authorization_url'], $errors['oauth_token_url'], $errors['oauth_userinfo_url'])
        ) {
            try {
                $endpoints = $this->oauthClient->discover($issuerUrl);
                $authorizationUrl ??= $endpoints['authorization'];
                $tokenUrl ??= $endpoints['token'];
                $userinfoUrl ??= $endpoints['userinfo'];
            } catch (ExternalLoginException $e) {
                $errors['oauth_issuer_url'] = 'Die Endpunkte konnten nicht automatisch ermittelt werden: ' . $e->getMessage();
            }
        }

        $scopes = trim((string) preg_replace('/\s+/', ' ', $command->oauthScopes));
        if ($scopes === '') {
            $scopes = ExternalLoginSettings::DEFAULT_OAUTH_SCOPES;
        } elseif (strlen($scopes) > 255 || preg_match('/^[A-Za-z0-9._:\/ -]+$/', $scopes) !== 1) {
            $errors['oauth_scopes'] = 'Bitte die Scopes durch Leerzeichen getrennt angeben, z. B. openid email profile.';
        }

        $emailClaim = self::identifier($command->oauthEmailClaim, 'email', '/^[A-Za-z0-9_.:\/-]+$/', 'oauth_email_claim', $errors);
        $firstNameClaim = self::identifier($command->oauthFirstNameClaim, 'given_name', '/^[A-Za-z0-9_.:\/-]+$/', 'oauth_first_name_claim', $errors);
        $lastNameClaim = self::identifier($command->oauthLastNameClaim, 'family_name', '/^[A-Za-z0-9_.:\/-]+$/', 'oauth_last_name_claim', $errors);

        if ($command->oauthEnabled) {
            if ($clientId === '') {
                $errors['oauth_client_id'] = 'Bitte die Client-ID angeben.';
            }
            $required = [
                'oauth_authorization_url' => $authorizationUrl,
                'oauth_token_url' => $tokenUrl,
                'oauth_userinfo_url' => $userinfoUrl,
            ];
            foreach ($required as $field => $value) {
                if ($value === null && !isset($errors[$field]) && !isset($errors['oauth_issuer_url'])) {
                    $errors[$field] = 'Bitte diese Adresse angeben oder die Aussteller-URL (Issuer) eintragen.';
                }
            }
        }

        // --- Automatically created users ------------------------------------
        $permissions = [];
        foreach ($command->defaultPermissions as $raw) {
            $permission = Permission::tryFrom($raw);
            if ($permission === null) {
                $errors['default_permissions'] = 'Ungültiges Recht ausgewählt.';
            } elseif ($permission === Permission::Administration) {
                $errors['default_permissions'] = 'Das Recht „Administration“ kann nicht automatisch vergeben werden.';
            } elseif (!in_array($permission, $permissions, true)) {
                $permissions[] = $permission;
            }
        }
        if ($command->autoCreateUsers && $permissions === [] && !isset($errors['default_permissions'])) {
            $errors['default_permissions'] = 'Bitte mindestens ein Recht für automatisch angelegte Benutzer auswählen.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settings->save(new ExternalLoginSettings(
            ldapEnabled: $command->ldapEnabled,
            ldapHost: $host !== '' ? $host : null,
            ldapPort: $port,
            ldapEncryption: $encryption,
            ldapBindDn: $bindDn !== '' ? $bindDn : null,
            ldapBindPassword: $bindPassword,
            ldapBaseDn: $baseDn !== '' ? $baseDn : null,
            ldapUserFilter: $filter,
            ldapEmailAttribute: $emailAttribute,
            ldapFirstNameAttribute: $firstNameAttribute,
            ldapLastNameAttribute: $lastNameAttribute,
            oauthEnabled: $command->oauthEnabled,
            oauthLabel: $label,
            oauthClientId: $clientId !== '' ? $clientId : null,
            oauthClientSecret: $clientSecret,
            oauthIssuerUrl: $issuerUrl,
            oauthAuthorizationUrl: $authorizationUrl,
            oauthTokenUrl: $tokenUrl,
            oauthUserinfoUrl: $userinfoUrl,
            oauthScopes: $scopes,
            oauthEmailClaim: $emailClaim,
            oauthFirstNameClaim: $firstNameClaim,
            oauthLastNameClaim: $lastNameClaim,
            oauthSkipTwoFactor: $command->oauthSkipTwoFactor,
            autoCreateUsers: $command->autoCreateUsers,
            defaultPermissions: $permissions,
        ));

        return null;
    }

    /** @param array<string, string> $errors */
    private static function limited(string $raw, int $maxLength, string $field, array &$errors): string
    {
        $value = trim($raw);
        if (strlen($value) > $maxLength) {
            $errors[$field] = sprintf('Die Eingabe ist zu lang (höchstens %d Zeichen).', $maxLength);
        }

        return $value;
    }

    /**
     * Attribute or claim name; empty input falls back to the default.
     *
     * @param array<string, string> $errors
     */
    private static function identifier(string $raw, string $default, string $pattern, string $field, array &$errors): string
    {
        $value = trim($raw);
        if ($value === '') {
            return $default;
        }
        if (strlen($value) > 100 || preg_match($pattern, $value) !== 1) {
            $errors[$field] = 'Ungültiger Name (erlaubt sind Buchstaben, Ziffern und Bindestriche).';
        }

        return $value;
    }

    /** @param array<string, string> $errors */
    private static function url(string $raw, string $field, array &$errors): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = parse_url($value, PHP_URL_HOST);
        if (
            strlen($value) > 500
            || filter_var($value, FILTER_VALIDATE_URL) === false
            || !in_array($scheme, ['https', 'http'], true)
            || !is_string($host)
            || $host === ''
        ) {
            $errors[$field] = 'Bitte eine vollständige Adresse angeben, die mit https:// beginnt.';
        }

        return $value;
    }
}
