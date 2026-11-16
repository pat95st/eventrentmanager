<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\User\Permission;
use App\Domain\User\User;

/**
 * Login through an external directory or identity provider (migration 171,
 * singleton row): LDAP / Active Directory or OAuth 2.0 / OpenID Connect as
 * an alternative to the local login. The administrator picks exactly one
 * login method. While an external one is active, the local login (password,
 * passkey) only remains as an emergency access for administrators, so that
 * a misconfiguration or an outage cannot lock everybody out.
 *
 * An external identity is mapped to a local user by its e-mail address.
 */
final class ExternalLoginSettings
{
    public const DEFAULT_LDAP_USER_FILTER = '(|(uid=%s)(mail=%s))';
    public const DEFAULT_OAUTH_LABEL = 'Single Sign-On';
    public const DEFAULT_OAUTH_SCOPES = 'openid email profile';

    /**
     * @param Permission[] $defaultPermissions permissions of users that are
     *        created automatically on their first external login
     */
    public function __construct(
        public LoginMethod $loginMethod = LoginMethod::Local,
        public ?string $ldapHost = null,
        public int $ldapPort = 389,
        public LdapEncryption $ldapEncryption = LdapEncryption::None,
        // Service account used to look up the user's DN. NULL = anonymous bind.
        public ?string $ldapBindDn = null,
        public ?string $ldapBindPassword = null,
        public ?string $ldapBaseDn = null,
        // "%s" is replaced by the (escaped) login name typed into the form.
        public string $ldapUserFilter = self::DEFAULT_LDAP_USER_FILTER,
        public string $ldapEmailAttribute = 'mail',
        public string $ldapFirstNameAttribute = 'givenName',
        public string $ldapLastNameAttribute = 'sn',
        // Text of the button on the login page.
        public string $oauthLabel = self::DEFAULT_OAUTH_LABEL,
        public ?string $oauthClientId = null,
        public ?string $oauthClientSecret = null,
        // Optional OpenID Connect issuer; used to discover the three endpoints.
        public ?string $oauthIssuerUrl = null,
        public ?string $oauthAuthorizationUrl = null,
        public ?string $oauthTokenUrl = null,
        public ?string $oauthUserinfoUrl = null,
        public string $oauthScopes = self::DEFAULT_OAUTH_SCOPES,
        public string $oauthEmailClaim = 'email',
        public string $oauthFirstNameClaim = 'given_name',
        public string $oauthLastNameClaim = 'family_name',
        // The identity provider already enforces a second factor, so the
        // local one is not asked for again after an OAuth login.
        public bool $oauthSkipTwoFactor = false,
        public bool $autoCreateUsers = false,
        public array $defaultPermissions = [],
    ) {
    }

    /** The login form checks user name and password against the directory. */
    public function ldapActive(): bool
    {
        return $this->loginMethod === LoginMethod::Ldap && $this->ldapHost !== null && $this->ldapBaseDn !== null;
    }

    /** The login page sends users to the identity provider. */
    public function oauthActive(): bool
    {
        return $this->loginMethod === LoginMethod::Oauth
            && $this->oauthClientId !== null
            && $this->oauthAuthorizationUrl !== null
            && $this->oauthTokenUrl !== null
            && $this->oauthUserinfoUrl !== null;
    }

    /**
     * The method that is really in effect. An external method with
     * incomplete settings (should not be storable) falls back to local.
     */
    public function activeMethod(): LoginMethod
    {
        return match (true) {
            $this->ldapActive() => LoginMethod::Ldap,
            $this->oauthActive() => LoginMethod::Oauth,
            default => LoginMethod::Local,
        };
    }

    /**
     * May this user log in with the local password or a passkey? Always
     * while the local login is the active method; otherwise only
     * administrators (emergency access).
     */
    public function allowsLocalLogin(User $user): bool
    {
        return $this->activeMethod() === LoginMethod::Local || $user->isAdministrator();
    }
}
