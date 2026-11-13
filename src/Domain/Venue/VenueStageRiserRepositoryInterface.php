<?php

declare(strict_types=1);

namespace App\Domain\Venue;

interface VenueStageRiserRepositoryInterface
{
    public function save(Riser $riser): void;

    public function findById(string $id): ?Riser;

    /**
     * Riser aller Bühnen eines Veranstaltungsorts in EINER Abfrage (kein N+1).
     *
     * @return array<string, Riser[]> Bühnen-ID => Riser in Anlegereihenfolge; Bühnen ohne Riser fehlen
     */
    public function findByVenueGroupedByStage(string $venueId): array;

    public function delete(string $id): void;
}
