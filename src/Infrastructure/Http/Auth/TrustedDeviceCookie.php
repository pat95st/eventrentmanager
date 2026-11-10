<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Application\Auth\TrustedDeviceService;

/**
 * Liest/schreibt das Cookie für "Dem Gerät für die nächsten 10 Tage
 * vertrauen" (siehe App\Application\Auth\TrustedDeviceService). HttpOnly
 * (kein Zugriff per JavaScript), SameSite=Lax und — sobald die Seite per
 * HTTPS aufgerufen wird — Secure.
 */
final class TrustedDeviceCookie
{
    public const NAME = 'mv_trusted_device';

    public static function read(): ?string
    {
        $value = $_COOKIE[self::NAME] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function write(string $token): void
    {
        self::send($token, time() + TrustedDeviceService::TRUST_DAYS * 86400);
        $_COOKIE[self::NAME] = $token;
    }

    public static function clear(): void
    {
        self::send('', time() - 3600);
        unset($_COOKIE[self::NAME]);
    }

    private static function send(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::NAME, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) === '443'
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
