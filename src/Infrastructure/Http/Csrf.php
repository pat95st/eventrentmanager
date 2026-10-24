<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Synchronizer-Token-Schutz gegen CSRF (Cross-Site Request Forgery). Das
 * Token liegt in der Session, analog zum bestehenden, sehr dünnen
 * $_SESSION-Zugriffsmuster mit lazy session_start() (siehe
 * App\Infrastructure\Http\Flash bzw. Auth\Session).
 *
 * Verwendung:
 *  - HTML-Formulare senden das Token als verstecktes Feld "csrf_token"
 *    (siehe resources/views/**, Helfer e(Csrf::token())).
 *  - Per fetch() ausgelöste Aktionen (Passkey-Registrierung/-Löschung, siehe
 *    layout.php für "window.CSRF_TOKEN") senden es stattdessen als Header
 *    "X-CSRF-Token".
 *  - Geprüft wird zentral in Router::dispatch() für jede zustandsändernde
 *    Anfrage (POST/PUT/PATCH/DELETE), nicht verstreut in den einzelnen
 *    Controllern — siehe dort für die genaue Begründung und Ausnahmen.
 */
final class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /** Liefert das aktuelle Token der Session, legt bei Bedarf ein neues an. */
    public static function token(): string
    {
        self::ensureStarted();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY]) || $_SESSION[self::SESSION_KEY] === '') {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Konstantzeit-Vergleich gegen das in der Session hinterlegte Token. */
    public static function verify(string $submitted): bool
    {
        self::ensureStarted();

        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($expected) || $expected === '' || $submitted === '') {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    /**
     * Erzwingt ein frisches Token, unabhängig davon, ob bereits eines
     * existiert. Wird nach erfolgreichem Login aufgerufen (siehe
     * Auth\Session::login()), zusätzlich zur dortigen
     * session_regenerate_id() — Session-Fixation-Härtung auch für das
     * CSRF-Token selbst.
     */
    public static function regenerate(): void
    {
        self::ensureStarted();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    private static function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
