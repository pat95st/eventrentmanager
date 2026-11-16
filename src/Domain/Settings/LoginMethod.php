<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * How users log in (see ExternalLoginSettings). Exactly one method is
 * active; LDAP and OAuth replace the local login instead of adding to it.
 */
enum LoginMethod: string
{
    // Password stored in this application, optionally passkeys.
    case Local = 'local';
    case Ldap = 'ldap';
    case Oauth = 'oauth';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Lokale Anmeldung (Passwort und Passkey)',
            self::Ldap => 'LDAP / Active Directory',
            self::Oauth => 'OAuth 2.0 / OpenID Connect',
        };
    }
}
