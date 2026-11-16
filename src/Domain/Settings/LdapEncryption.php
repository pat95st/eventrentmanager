<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/** Transport security of the LDAP connection (see ExternalLoginSettings). */
enum LdapEncryption: string
{
    case None = 'none';
    case StartTls = 'starttls';
    case Ldaps = 'ldaps';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Keine (unverschlüsselt)',
            self::StartTls => 'STARTTLS',
            self::Ldaps => 'LDAPS (SSL/TLS)',
        };
    }

    public function defaultPort(): int
    {
        return $this === self::Ldaps ? 636 : 389;
    }
}
