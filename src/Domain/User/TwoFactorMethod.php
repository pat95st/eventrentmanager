<?php

declare(strict_types=1);

namespace App\Domain\User;

/**
 * Der optionale zweite Faktor bei der Anmeldung: keiner, eine
 * Authenticator-App (TOTP, RFC 6238) oder ein per E-Mail zugesandter Code.
 */
enum TwoFactorMethod: string
{
    case None = 'none';
    case Totp = 'totp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Kein zweiter Faktor',
            self::Totp => 'Authenticator-App (TOTP)',
            self::Email => 'Code per E-Mail',
        };
    }
}
