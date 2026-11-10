<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

interface AudioPairingCodeRepositoryInterface
{
    public function save(AudioPairingCode $code): void;

    /** Noch nicht benutzter, nicht abgelaufener Code (oder null). */
    public function findUsable(string $code, DateTimeImmutable $now): ?AudioPairingCode;

    /** Aktiver Code einer Veranstaltung zur Anzeige (oder null). */
    public function findActiveForRental(string $rentalId, DateTimeImmutable $now): ?AudioPairingCode;

    /** Alle offenen Codes einer Veranstaltung verwerfen (beim Erzeugen eines neuen Codes). */
    public function invalidateForRental(string $rentalId, DateTimeImmutable $now): void;

    public function recordFailure(string $ip, DateTimeImmutable $at): void;

    public function countFailuresSince(string $ip, DateTimeImmutable $since): int;
}
