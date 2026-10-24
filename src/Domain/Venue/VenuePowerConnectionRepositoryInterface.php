<?php

declare(strict_types=1);

namespace App\Domain\Venue;

interface VenuePowerConnectionRepositoryInterface
{
    public function save(PowerConnection $connection): void;

    public function findById(string $id): ?PowerConnection;

    /** @return PowerConnection[] */
    public function findByVenue(string $venueId): array;

    /**
     * Für die Warnbadge in der Veranstaltungsort-Liste (venues/_table.php):
     * lädt die Anschlüsse mehrerer Veranstaltungsorte in EINER Abfrage
     * (statt pro Zeile einzeln nachzuladen — vermeidet N+1), gruppiert nach
     * venue_id.
     *
     * @param string[] $venueIds
     * @return array<string, PowerConnection[]> venueId => Anschlüsse
     */
    public function findByVenues(array $venueIds): array;

    public function delete(string $id): void;
}
