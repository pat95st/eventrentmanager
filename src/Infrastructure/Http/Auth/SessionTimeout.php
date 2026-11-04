<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

/**
 * Automatische Abmeldung nach Inaktivität (Migration 127, Einstellungen ›
 * Sicherheit). Wird in bootstrap.php einmal pro Anfrage mit dem
 * eingestellten Wert konfiguriert, BEVOR eine Session gestartet wird:
 *
 *  - Maßgeblich für "inaktiv" ist user_sessions.last_seen_at (wird bei jeder
 *    angemeldeten Anfrage aufgefrischt, siehe AuthGuard).
 *  - Damit PHP die Sitzungsdateien nicht schon früher wegräumt (Standard
 *    sind 24 Minuten, auf Debian/Ubuntu zusätzlich per Cronjob nach den
 *    php.ini-Werten), liegen die Sitzungen in einem eigenen Verzeichnis
 *    (storage/sessions) mit passender Lebensdauer.
 */
final class SessionTimeout
{
    private static int $seconds = 7200;

    public static function configure(int $minutes, ?string $savePath = null): void
    {
        self::$seconds = max(60, $minutes * 60);

        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }
        if ($savePath !== null && (is_dir($savePath) || @mkdir($savePath, 0700, true)) && is_writable($savePath)) {
            ini_set('session.save_path', $savePath);
            // Eigenes Verzeichnis: kein System-Cronjob räumt hier auf, also
            // PHP-eigene Aufräumroutine aktivieren (ca. jede 100. Anfrage).
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
        // Etwas Puffer, damit die Abmeldung über AuthGuard (mit Hinweis)
        // greift und nicht stillschweigend die Sitzungsdatei verschwindet.
        ini_set('session.gc_maxlifetime', (string) (self::$seconds + 600));
    }

    public static function seconds(): int
    {
        return self::$seconds;
    }

    public static function minutes(): int
    {
        return intdiv(self::$seconds, 60);
    }

    /** Verbleibende Sekunden bis zur Abmeldung, ausgehend von der letzten Aktivität. */
    public static function remaining(\DateTimeImmutable $lastSeenAt, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();

        return self::$seconds - ($now->getTimestamp() - $lastSeenAt->getTimestamp());
    }
}
