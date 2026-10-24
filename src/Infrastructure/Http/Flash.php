<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Sehr simple Session-basierte Flash-Messages für "Gespeichert."/Fehler-Hinweise
 * nach einem Redirect (POST/Redirect/GET-Muster).
 */
final class Flash
{
    public static function success(string $message): void
    {
        self::ensureSession();
        $_SESSION['flash_success'] = $message;
    }

    public static function error(string $message): void
    {
        self::ensureSession();
        $_SESSION['flash_error'] = $message;
    }

    public static function consumeSuccess(): ?string
    {
        self::ensureSession();
        $message = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_success']);

        return $message;
    }

    public static function consumeError(): ?string
    {
        self::ensureSession();
        $message = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        return $message;
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
