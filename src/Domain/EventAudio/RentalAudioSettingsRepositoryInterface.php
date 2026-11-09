<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

interface RentalAudioSettingsRepositoryInterface
{
    /** Liefert Standardwerte, wenn für die Veranstaltung noch nichts gespeichert ist. */
    public function get(string $rentalId): RentalAudioSettings;

    public function save(RentalAudioSettings $settings): void;

    /**
     * Veranstaltung, die das Gerät gerade bespielt: zugeordnet und
     * Veranstaltungszeitraum (inkl. je einem Tag Puffer für Aufbau/Abbau)
     * umfasst $now. Bei mehreren die mit dem frühesten Beginn.
     */
    public function activeRentalIdForPlayer(string $playerId, DateTimeImmutable $now): ?string;
}
