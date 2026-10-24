<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Leitet die vollständige Origin (Schema + Host + ggf. Port) der aktuellen
 * Anfrage aus HTTP_HOST ab — keine hart kodierte Domain (siehe
 * Projektvorgabe). Identische Logik wie PasskeyController::origin(), hier
 * als wiederverwendbarer Helfer, damit z. B. UserController und
 * AuthController beim Versand von Setup-Password-Links (siehe
 * App\Application\Auth\PasswordSetupTokenIssuer) nicht jeweils eine eigene
 * Kopie pflegen müssen.
 */
final class Origin
{
    public static function current(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) === '443'
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        return ($isHttps ? 'https://' : 'http://') . $host;
    }
}
