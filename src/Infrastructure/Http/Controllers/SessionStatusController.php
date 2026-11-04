<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Domain\Auth\UserSessionRepositoryInterface;
use App\Domain\Settings\SecuritySettings;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Auth\SessionTimeout;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Für den Hinweis vor der automatischen Abmeldung (public/assets/js/
 * session-timeout.js):
 *  - GET /sitzung/status — verbleibende Zeit; bewusst OHNE AuthGuard, damit
 *    die Abfrage selbst nicht als Aktivität zählt.
 *  - GET /sitzung/verlaengern — mit AuthGuard (der frischt last_seen_at auf),
 *    "Angemeldet bleiben".
 */
final class SessionStatusController
{
    public function __construct(private readonly UserSessionRepositoryInterface $sessions)
    {
    }

    public function status(Request $request): void
    {
        $remaining = $this->remaining();
        if ($remaining !== null && $remaining <= 0) {
            // Abgelaufen: hier schon abmelden (wie AuthGuard), damit die Seite
            // direkt zur Anmeldung mit Hinweis wechseln kann und die zuletzt
            // offene Seite danach wieder geöffnet wird.
            $sessionId = session_id();
            if ($sessionId !== false && $sessionId !== '') {
                $this->sessions->deleteById($sessionId);
            }
            Session::logout();
            Flash::error('Du wurdest nach ' . SecuritySettings::formatMinutes(SessionTimeout::minutes()) . ' ohne Aktivität automatisch abgemeldet. Bitte melde dich erneut an.');
            $path = (string) ($request->query('seite') ?? '');
            if (str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_starts_with($path, '/login')) {
                Session::setRedirectAfterLogin($path);
            }
        }
        Response::json([
            'loggedIn' => $remaining !== null && $remaining > 0,
            'remaining' => max(0, $remaining ?? 0),
            'timeout' => SessionTimeout::seconds(),
        ]);
    }

    public function extend(Request $request): void
    {
        Response::json(['loggedIn' => true, 'remaining' => SessionTimeout::seconds(), 'timeout' => SessionTimeout::seconds()]);
    }

    private function remaining(): ?int
    {
        if (Session::userId() === null) {
            return null;
        }
        $sessionId = session_id();
        $session = $sessionId !== false && $sessionId !== '' ? $this->sessions->findById($sessionId) : null;

        return $session !== null ? SessionTimeout::remaining($session->lastSeenAt) : null;
    }
}
