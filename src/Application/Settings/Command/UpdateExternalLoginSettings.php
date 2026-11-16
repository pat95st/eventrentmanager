<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/**
 * Raw form values of Einstellungen › Anmeldung (LDAP/OAuth). $loginMethod
 * selects the one active login method (LoginMethod). The two secrets
 * (LDAP bind password, OAuth client secret) keep their stored value when
 * submitted empty.
 */
final class UpdateExternalLoginSettings implements CommandInterface
{
    /** @param string[] $defaultPermissions */
    public function __construct(
        public readonly string $loginMethod = 'local',
        public readonly string $ldapHost = '',
        public readonly string $ldapPort = '',
        public readonly string $ldapEncryption = 'none',
        public readonly string $ldapBindDn = '',
        public readonly string $ldapBindPassword = '',
        public readonly string $ldapBaseDn = '',
        public readonly string $ldapUserFilter = '',
        public readonly string $ldapEmailAttribute = '',
        public readonly string $ldapFirstNameAttribute = '',
        public readonly string $ldapLastNameAttribute = '',
        public readonly string $oauthLabel = '',
        public readonly string $oauthClientId = '',
        public readonly string $oauthClientSecret = '',
        public readonly string $oauthIssuerUrl = '',
        public readonly string $oauthAuthorizationUrl = '',
        public readonly string $oauthTokenUrl = '',
        public readonly string $oauthUserinfoUrl = '',
        public readonly string $oauthScopes = '',
        public readonly string $oauthEmailClaim = '',
        public readonly string $oauthFirstNameClaim = '',
        public readonly string $oauthLastNameClaim = '',
        public readonly bool $oauthSkipTwoFactor = false,
        public readonly bool $autoCreateUsers = false,
        public readonly array $defaultPermissions = [],
    ) {
    }
}
