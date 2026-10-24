<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use DateTimeImmutable;

/**
 * Ein CalDAV-App-Passwort eines Users (Selbstverwaltung, siehe
 * ProfileController). Analog zu App\Domain\Api\ApiToken: Basic-Auth-taugliche
 * Zugangsdaten für Kalender-Apps, getrennt vom normalen Anmeldepasswort und
 * einzeln widerrufbar. $tokenHash ist SHA-256 des Klartext-Passworts (wie
 * bei ApiToken — siehe CreateCalDavCredentialHandler), niemals das
 * Klartext-Passwort selbst.
 */
final class CalDavCredential
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
