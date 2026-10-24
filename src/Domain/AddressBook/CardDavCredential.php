<?php

declare(strict_types=1);

namespace App\Domain\AddressBook;

use DateTimeImmutable;

/**
 * Ein CardDAV-App-Passwort eines Users (Selbstverwaltung, siehe
 * ProfileController). Eigenständig von App\Domain\Calendar\CalDavCredential
 * (eigene Tabelle, eigene Widerrufsliste) — bewusste Entscheidung, damit ein
 * widerrufenes Kalender-Passwort nicht versehentlich auch den Kontaktzugriff
 * einer App entzieht und umgekehrt. Ansonsten strukturell identisch: Basic-
 * Auth-taugliche Zugangsdaten, getrennt vom normalen Anmeldepasswort,
 * einzeln widerrufbar. $tokenHash ist SHA-256 des Klartext-Passworts (wie
 * bei CalDavCredential/ApiToken — siehe CreateCardDavCredentialHandler),
 * niemals das Klartext-Passwort selbst.
 */
final class CardDavCredential
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public string $name,
        public readonly string $tokenHash,
        public readonly string $tokenLastFour,
        public ?DateTimeImmutable $lastUsedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
