<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Domain\Auth\UserSession;
use App\Domain\Auth\UserSessionRepositoryInterface;
use DateTimeImmutable;

/**
 * Kleiner, gebündelter Nachbereitungsschritt für JEDEN erfolgreichen
 * "vollständigen" Login (siehe Session::login()): legt die zugehörige
 * user_sessions-Zeile an, mit der NEUEN (nach session_regenerate_id()
 * erzeugten) Session-ID — daher MUSS record() erst NACH Session::login()
 * aufgerufen werden, niemals davor.
 *
 * Es gibt in diesem Projekt mehrere gleichwertige "Login abschließen"-
 * Stellen (Passwort-Login ohne 2FA, Zwei-Faktor-Bestätigung, Passkey-Login,
 * Passwort-Einrichtung/Einladung — siehe AuthController, PasskeyController,
 * SetupPasswordController), aber keinen bereits bestehenden gemeinsamen
 * "Login abschließen"-Service, der sie bündelt. Statt die
 * user_sessions-Erfassung in allen vier Controllern zu duplizieren, rufen
 * alle diesen einen Helfer auf.
 */
final class RecordUserSession
{
    public function __construct(private readonly UserSessionRepositoryInterface $sessions)
    {
    }

    public function record(string $userId): void
    {
        Session::ensureStarted();

        $sessionId = session_id();
        if ($sessionId === false || $sessionId === '') {
            // Sollte praktisch nie vorkommen (ensureStarted() garantiert eine
            // aktive Session) — sicherheitshalber nichts tun, statt mit einer
            // leeren ID eine falsche Zeile anzulegen.
            return;
        }

        $now = new DateTimeImmutable();

        $this->sessions->upsert(new UserSession(
            id: $sessionId,
            userId: $userId,
            userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
            ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            createdAt: $now,
            lastSeenAt: $now,
        ));
    }
}
