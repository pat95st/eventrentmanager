<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Domain\User\User;

/**
 * Statischer, request-gebundener Speicher für den aktuell angemeldeten
 * Benutzer (von AuthGuard vor Aufruf des eigentlichen Controllers gesetzt).
 * Erlaubt View::page(), den Benutzer für die Kopfzeile/Navigation
 * automatisch mitzuliefern, ohne dass jeder der ~20 Controller ihn
 * explizit durchreichen müsste — analog zum bestehenden statischen
 * Flash-Mechanismus.
 */
final class CurrentUser
{
    private static ?User $current = null;

    public static function set(?User $user): void
    {
        self::$current = $user;
    }

    public static function get(): ?User
    {
        return self::$current;
    }
}
