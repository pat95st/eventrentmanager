<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\User\User;
use App\Infrastructure\Http\Auth\Session;

/**
 * Statischer, request-gebundener Speicher für die aktive Oberflächensprache,
 * analog zu Auth\CurrentUser bzw. LegalFooterLinks. Wird einmal pro Request
 * in bootstrap.php aus der Session vorbelegt (für anonyme Seiten wie den
 * Login) und danach von AuthGuard::guard() ggf. auf die in der Datenbank
 * hinterlegte Präferenz des angemeldeten Benutzers überschrieben.
 * Translator::t() liest ausschließlich hier, damit kein Aufrufer die
 * Sprache selbst durchreichen muss.
 */
final class Locale
{
    public const DEFAULT = 'de';

    /** @var string[] */
    public const SUPPORTED = ['de', 'en'];

    private static string $current = self::DEFAULT;

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public static function set(string $locale): void
    {
        self::$current = self::isSupported($locale) ? $locale : self::DEFAULT;
    }

    public static function get(): string
    {
        return self::$current;
    }

    /**
     * Request-weite Erstauflösung: aus der Session (falls dort bereits per
     * Sprachumschalter oder vorherigem Login gesetzt), sonst Standard
     * Deutsch. Für angemeldete Benutzer wird dies anschließend über
     * setFromUser() von der in der Datenbank hinterlegten Präferenz
     * überschrieben (siehe AuthGuard::guard()).
     */
    public static function resolveFromSession(): void
    {
        Session::ensureStarted();
        $stored = $_SESSION['locale'] ?? null;
        self::set(is_string($stored) ? $stored : self::DEFAULT);
    }

    /**
     * Übernimmt die Sprachpräferenz des angemeldeten Benutzers und
     * spiegelt sie zusätzlich in die Session, damit z. B. die
     * Zwei-Faktor-Zwischenseite (noch kein vollständig angemeldeter
     * Benutzer) bereits die richtige Sprache zeigt.
     */
    public static function setFromUser(User $user): void
    {
        self::set($user->locale);
        Session::ensureStarted();
        $_SESSION['locale'] = self::$current;
    }

    /** Setzt nur die Session (für anonyme Seiten / den Sprachumschalter vor dem Login). */
    public static function rememberInSession(string $locale): void
    {
        self::set($locale);
        Session::ensureStarted();
        $_SESSION['locale'] = self::$current;
    }
}
