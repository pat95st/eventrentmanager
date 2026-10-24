<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Zentrale, bewusst einfache Passwort-Mindestanforderung (nur Mindestlänge,
 * keine erzwungenen Sonderzeichen/Ziffern-Regeln — solche Regeln führen laut
 * gängiger aktueller Empfehlungen (u. a. NIST SP 800-63B) eher zu
 * vorhersehbaren Mustern als zu tatsächlich stärkeren Passwörtern; die
 * Mindestlänge ist der wirksamere Hebel). Genutzt von CreateUserHandler,
 * ChangePasswordHandler und ChangeOwnPasswordHandler, damit die Regel nicht
 * an drei Stellen auseinanderlaufen kann.
 */
final class PasswordPolicy
{
    private const MIN_LENGTH = 10;

    public static function validate(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return sprintf('Das Passwort muss mindestens %d Zeichen lang sein.', self::MIN_LENGTH);
        }

        return null;
    }
}
