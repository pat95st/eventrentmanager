<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Infrastructure\Http\Csrf;

/**
 * Sehr dünner Wrapper um $_SESSION, analog zu App\Infrastructure\Http\Flash
 * (dort ebenfalls direkter $_SESSION-Zugriff mit lazy session_start()).
 * Bündelt nur die Auth-relevanten Schlüssel, damit sie nicht als
 * Magic-Strings über mehrere Controller verstreut sind.
 */
final class Session
{
    private const KEY_USER_ID = 'auth_user_id';
    private const KEY_PENDING_2FA_USER_ID = 'auth_pending_2fa_user_id';
    private const KEY_REDIRECT_AFTER_LOGIN = 'auth_redirect_after_login';
    private const KEY_TOTP_SETUP_SECRET = 'auth_totp_setup_secret';
    /**
     * Gesetzt, während ein Administrator sich als ein anderer Benutzer
     * ausgibt ("Impersonation") — trägt dessen ursprüngliche User-ID, damit
     * sich die Session jederzeit wieder auf den Administrator zurückstellen
     * lässt (siehe stopImpersonation()). KEY_USER_ID zeigt währenddessen auf
     * den Zielbenutzer, exakt wie bei einer normalen Anmeldung als dieser
     * Benutzer.
     */
    private const KEY_IMPERSONATOR_ID = 'auth_impersonator_user_id';
    /** ID des zugehörigen Protokoll-Datensatzes (App\Domain\Auth\Impersonation), für StopImpersonation. */
    private const KEY_IMPERSONATION_ID = 'auth_impersonation_id';

    public static function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function userId(): ?string
    {
        self::ensureStarted();

        return $_SESSION[self::KEY_USER_ID] ?? null;
    }

    /**
     * Vollständige Anmeldung abschließen: setzt die User-ID und erneuert die
     * Session-ID sowie das CSRF-Token (Schutz vor Session Fixation bzw.
     * Login-CSRF, siehe Csrf::regenerate()).
     */
    public static function login(string $userId): void
    {
        self::ensureStarted();
        session_regenerate_id(true);
        $_SESSION[self::KEY_USER_ID] = $userId;
        unset($_SESSION[self::KEY_PENDING_2FA_USER_ID]);
        // Sicherheitsrelevant: session_regenerate_id() erneuert nur die
        // Session-ID, nicht den Inhalt von $_SESSION. Ohne dieses unset()
        // könnten Reste einer vorherigen Impersonation (KEY_IMPERSONATOR_ID/
        // KEY_IMPERSONATION_ID) in der Sitzung stehen bleiben, falls sich
        // jemand erneut einloggt, ohne vorher auszuloggen (POST /login,
        // /login/zwei-faktor, Passkey- oder Setup-Passwort-Login sind
        // bewusst NICHT durch AuthGuard geschützt, siehe
        // AuthGuard::PUBLIC_PATHS). Andernfalls würde "Zurück zu eigenem
        // Konto" (stopImpersonation()) die Sitzung anschließend ohne jede
        // Passwortprüfung auf den ALTEN Administrator umschalten —
        // eine Rechteausweitung auf dessen Konto.
        unset($_SESSION[self::KEY_IMPERSONATOR_ID], $_SESSION[self::KEY_IMPERSONATION_ID]);
        Csrf::regenerate();
    }

    public static function logout(): void
    {
        self::ensureStarted();
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /** Erste Stufe (Passwort korrekt, zweiter Faktor noch offen) — bewusst getrennt von KEY_USER_ID. */
    public static function startTwoFactorChallenge(string $userId): void
    {
        self::ensureStarted();
        $_SESSION[self::KEY_PENDING_2FA_USER_ID] = $userId;
    }

    public static function pendingTwoFactorUserId(): ?string
    {
        self::ensureStarted();

        return $_SESSION[self::KEY_PENDING_2FA_USER_ID] ?? null;
    }

    public static function setRedirectAfterLogin(string $path): void
    {
        self::ensureStarted();
        $_SESSION[self::KEY_REDIRECT_AFTER_LOGIN] = $path;
    }

    public static function consumeRedirectAfterLogin(): string
    {
        self::ensureStarted();
        $path = $_SESSION[self::KEY_REDIRECT_AFTER_LOGIN] ?? '/';
        unset($_SESSION[self::KEY_REDIRECT_AFTER_LOGIN]);

        return $path;
    }

    /**
     * Hält das während der TOTP-Einrichtung angezeigte Secret vor, bis der
     * Bestätigungscode eingegeben wurde (siehe ProfileController) — noch
     * nicht in der Datenbank gespeichert, damit ein abgebrochener
     * Einrichtungsversuch keinen halb konfigurierten Faktor hinterlässt.
     */
    public static function setPendingTotpSecret(string $secret): void
    {
        self::ensureStarted();
        $_SESSION[self::KEY_TOTP_SETUP_SECRET] = $secret;
    }

    public static function pendingTotpSecret(): ?string
    {
        self::ensureStarted();

        return $_SESSION[self::KEY_TOTP_SETUP_SECRET] ?? null;
    }

    public static function clearPendingTotpSecret(): void
    {
        self::ensureStarted();
        unset($_SESSION[self::KEY_TOTP_SETUP_SECRET]);
    }

    /**
     * Schaltet die laufende Sitzung auf den Zielbenutzer um, merkt sich aber
     * die ursprüngliche Administrator-ID sowie die ID des Protokoll-
     * Datensatzes. Bewusst OHNE session_regenerate_id(): es handelt sich
     * nicht um einen neuen Anmeldevorgang (der Administrator ist bereits
     * durch seine eigene, vollständige Anmeldung inkl. 2FA authentifiziert),
     * sondern nur um einen Rechte-Wechsel innerhalb derselben, bereits
     * authentifizierten Sitzung — eine neue Session-ID hätte außerdem zur
     * Folge, dass die serverseitige Sitzungs-Registrierung
     * (App\Domain\Auth\UserSession, siehe AuthGuard::guard()) die
     * ursprüngliche Admin-Sitzung verwaist zurücklässt.
     */
    public static function startImpersonation(string $impersonationId, string $adminUserId, string $targetUserId): void
    {
        self::ensureStarted();
        $_SESSION[self::KEY_IMPERSONATOR_ID] = $adminUserId;
        $_SESSION[self::KEY_IMPERSONATION_ID] = $impersonationId;
        $_SESSION[self::KEY_USER_ID] = $targetUserId;
    }

    public static function isImpersonating(): bool
    {
        self::ensureStarted();

        return isset($_SESSION[self::KEY_IMPERSONATOR_ID]);
    }

    public static function impersonatorUserId(): ?string
    {
        self::ensureStarted();

        return $_SESSION[self::KEY_IMPERSONATOR_ID] ?? null;
    }

    public static function impersonationId(): ?string
    {
        self::ensureStarted();

        return $_SESSION[self::KEY_IMPERSONATION_ID] ?? null;
    }

    /**
     * Beendet die Impersonation und stellt die ursprüngliche
     * Administrator-Identität wieder her. Liefert dessen User-ID zurück
     * (oder null, falls gar keine Impersonation lief), damit der Controller
     * weiß, wohin umgeleitet werden kann.
     */
    public static function stopImpersonation(): ?string
    {
        self::ensureStarted();

        $adminUserId = $_SESSION[self::KEY_IMPERSONATOR_ID] ?? null;
        if ($adminUserId === null) {
            return null;
        }

        $_SESSION[self::KEY_USER_ID] = $adminUserId;
        unset($_SESSION[self::KEY_IMPERSONATOR_ID], $_SESSION[self::KEY_IMPERSONATION_ID]);

        return $adminUserId;
    }
}
