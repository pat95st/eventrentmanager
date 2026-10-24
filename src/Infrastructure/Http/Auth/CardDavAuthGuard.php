<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Domain\AddressBook\CardDavCredentialRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

/**
 * HTTP-Basic-Auth-Prüfung für den CardDAV-Server — strukturell identisch zu
 * CalDavAuthGuard (siehe dort für die ausführliche Begründung), aber gegen
 * die eigenständige carddav_credentials-Tabelle geprüft (siehe
 * App\Domain\AddressBook\CardDavCredential): ein CalDAV-App-Passwort
 * berechtigt bewusst NICHT zum Kontaktzugriff und umgekehrt.
 */
final class CardDavAuthGuard
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CardDavCredentialRepositoryInterface $credentials,
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
        header('WWW-Authenticate: Basic realm="Materialverleih CardDAV"');
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Anmeldung erforderlich.';
        exit;
    }
}
