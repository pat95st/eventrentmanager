<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Domain\Auth\UserSession;
use App\Domain\Auth\UserSessionRepositoryInterface;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Http\Locale;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use DateTimeImmutable;

/**
 * Wrappt Routen-Handler mit einer Anmelde-/Rechteprüfung, siehe
 * src/bootstrap.php für die Verdrahtung. Bewusst als Handler-Wrapper statt
 * als Router-Middleware-System umgesetzt, damit Router.php unverändert
 * bleiben kann (jede Route entscheidet selbst per guard()-Aufruf, ob und
 * welches Recht sie braucht).
 */
final class AuthGuard
{
    /** Pfade, die IMMER ohne Anmeldung erreichbar sein müssen (sonst könnte sich niemand mehr einloggen). */
    private const PUBLIC_PATHS = [
        '/login',
        '/login/zwei-faktor',
        '/login/zwei-faktor/code-erneut-senden',
        '/passkeys/login/options',
        '/passkeys/login/complete',
    ];

    /** Pfad der erzwungenen Passwortänderung — bleibt erreichbar, auch wenn mustChangePassword aktiv ist. */
    private const FORCED_PASSWORD_CHANGE_PATH = '/profil/passwort';

    /**
     * Pfad, über den eine laufende Impersonation beendet wird (siehe
     * UserController::stopImpersonation()). Muss IMMER erreichbar bleiben,
     * solange tatsächlich impersoniert wird — unabhängig davon, ob der
     * Zielbenutzer selbst die nötigen Rechte dafür hätte, einen erzwungenen
     * Passwortwechsel/2FA-Setup offen hat, o. Ä. Ein Administrator, der sich
     * als jemanden ohne Administration-Recht ausgibt, muss sich jederzeit
     * wieder zurückschalten können.
     */
    private const IMPERSONATION_STOP_PATH = '/benutzer/impersonation/beenden';

    /**
     * Pfade, die trotz erzwungener Zwei-Faktor-Einrichtung (mustSetupTwoFactor)
     * erreichbar bleiben müssen: die bestehende Selbstbedienungs-Seite
     * "Sicherheit" (siehe ProfileController::showSecurity() — dort können
     * TOTP/E-Mail-Code bereits eingerichtet werden, es war keine neue Seite
     * nötig) sowie deren POST-Endpunkte zum tatsächlichen Aktivieren.
     */
    private const FORCED_TWO_FACTOR_SETUP_PATHS = [
        '/profil/sicherheit',
        '/profil/sicherheit/totp',
        '/profil/sicherheit/email',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserSessionRepositoryInterface $sessions,
    ) {
    }

    public function guard(?Permission $permission, callable $handler): callable
    {
        return function (Request $request) use ($permission, $handler): void {
            Session::ensureStarted();

            $userId = Session::userId();
            if ($userId === null) {
                Session::setRedirectAfterLogin($request->path);
                Response::redirect('/login');

                return;
            }

            $user = $this->users->findById($userId);
            if ($user === null || !$user->isActive) {
                // Konto wurde in der Zwischenzeit gelöscht/deaktiviert — Session verwerfen.
                Session::logout();
                Response::redirect('/login');

                return;
            }

            // Prüft, ob DIESE Session-ID noch als aktiv gilt (serverseitiges
            // Register, siehe App\Domain\Auth\UserSession) — fehlt die Zeile,
            // wurde die Sitzung von einem anderen Gerät/Tab aus beendet
            // (siehe RevokeSessionHandler/RevokeOtherSessionsHandler). Die
            // PHP-Session-Datei selbst wurde dabei NICHT gelöscht, daher wird
            // hier genau wie bei einem gelöschten/deaktivierten Konto
            // reagiert: lokal abmelden und zum Login schicken. Existiert die
            // Zeile noch, wird bei dieser Gelegenheit gleich last_seen_at
            // aufgefrischt ("zuletzt aktiv").
            $sessionId = session_id();
            $currentSession = $sessionId !== false && $sessionId !== '' ? $this->sessions->findById($sessionId) : null;

            if ($currentSession === null) {
                Session::logout();
                Response::redirect('/login');

                return;
            }

            $this->sessions->upsert(new UserSession(
                id: $currentSession->id,
                userId: $currentSession->userId,
                userAgent: $_SERVER['HTTP_USER_AGENT'] ?? $currentSession->userAgent,
                ipAddress: $_SERVER['REMOTE_ADDR'] ?? $currentSession->ipAddress,
                createdAt: $currentSession->createdAt,
                lastSeenAt: new DateTimeImmutable(),
            ));

            CurrentUser::set($user);
            Locale::setFromUser($user);

            if (Session::isImpersonating()) {
                ImpersonationContext::set($this->users->findById(Session::impersonatorUserId() ?? ''));

                // Das Beenden einer Impersonation muss unabhängig von den
                // sonstigen Guards (Recht, erzwungener Passwortwechsel/
                // 2FA-Setup des ZIELBENUTZERS) immer möglich sein — sonst
                // könnte sich ein Administrator, der sich z. B. als
                // Benutzer ohne Administration-Recht ausgibt, nicht mehr
                // selbst zurückschalten.
                if ($request->path === self::IMPERSONATION_STOP_PATH) {
                    $handler($request);

                    return;
                }
            } else {
                ImpersonationContext::set(null);
            }

            if ($user->mustChangePassword && $request->path !== self::FORCED_PASSWORD_CHANGE_PATH) {
                Response::redirect(self::FORCED_PASSWORD_CHANGE_PATH);

                return;
            }

            // Analog zur erzwungenen Passwortänderung: solange noch kein
            // zweiter Faktor eingerichtet wurde, bleibt nur die
            // Sicherheits-Seite erreichbar. Hört automatisch auf zu greifen,
            // sobald ein Faktor eingerichtet ist (twoFactorMethod !== None)
            // — mustSetupTwoFactor selbst muss dafür nicht zurückgesetzt werden.
            if (
                $user->mustSetupTwoFactor
                && $user->twoFactorMethod === TwoFactorMethod::None
                && !in_array($request->path, self::FORCED_TWO_FACTOR_SETUP_PATHS, true)
            ) {
                Response::redirect('/profil/sicherheit');

                return;
            }

            if ($permission !== null && !$user->has($permission)) {
                http_response_code(403);
                echo View::page('errors/403', [], activeNav: '', title: 'Kein Zugriff');

                return;
            }

            $handler($request);
        };
    }

    public static function isPublicPath(string $path): bool
    {
        return in_array($path, self::PUBLIC_PATHS, true);
    }
}
