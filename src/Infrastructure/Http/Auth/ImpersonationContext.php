<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Domain\User\User;

/**
 * Statischer, request-gebundener Speicher für den ursprünglichen
 * Administrator während einer laufenden Impersonation (von AuthGuard vor
 * Aufruf des eigentlichen Controllers gesetzt, analog zu CurrentUser).
 * null, solange keine Impersonation aktiv ist. Erlaubt layout.php, den
 * Hinweis-Banner ("Angemeldet als …, ausgegeben von …") zu rendern, ohne
 * dass jeder Controller das selbst durchreichen müsste.
 */
final class ImpersonationContext
{
    private static ?User $impersonator = null;

    public static function set(?User $impersonator): void
    {
        self::$impersonator = $impersonator;
    }

    public static function get(): ?User
    {
        return self::$impersonator;
    }
}
