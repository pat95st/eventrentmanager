<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Domain\Calendar\CalDavCredentialRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

/**
 * HTTP-Basic-Auth-Prüfung für den CalDAV-Server (bewusst getrennt von
 * AuthGuard [Session-Cookie, HTML-Frontend] und ApiTokenGuard [Bearer-Token,
 * REST-API] — CalDAV-Clients (Apple Kalender, Thunderbird, ...) unterstützen
 * standardmäßig nur Basic Auth). Zugangsdaten sind KEIN Anmeldepasswort,
 * sondern ein separates, pro User selbst verwaltetes App-Passwort (siehe
 * App\Domain\Calendar\CalDavCredential / ProfileController).
 *
 * Anders als ApiTokenGuard::guard() (das einen Handler wrappt) liefert
 * authenticate() direkt den authentifizierten User zurück oder beendet die
 * Anfrage selbst mit 401 — CalDavController prüft danach zusätzlich, ob der
 * User Zugriff auf den jeweils angefragten Kalender hat (siehe dort).
 */
final class CalDavAuthGuard
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CalDavCredentialRepositoryInterface $credentials,
    ) {
    }

    public function authenticate(): User
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if (!is_string($header) || !str_starts_with($header, 'Basic ')) {
            $this->requireAuth();
        }

        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            $this->requireAuth();
        }

        [$email, $password] = explode(':', $decoded, 2);

        $user = $this->users->findByEmail($email);
        if ($user === null || !$user->isActive) {
            $this->requireAuth();
        }

        $credential = $this->credentials->findByTokenHash(hash('sha256', $password));
        if ($credential === null || $credential->userId !== $user->id) {
            $this->requireAuth();
        }

        $this->credentials->touchLastUsed($credential->id, new DateTimeImmutable());

        return $user;
    }

    private function requireAuth(): never
    {
        header('WWW-Authenticate: Basic realm="Materialverleih CalDAV"');
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Anmeldung erforderlich.';
        exit;
    }
}
