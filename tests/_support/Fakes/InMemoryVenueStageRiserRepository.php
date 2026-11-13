<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Venue\Riser;
use App\Domain\Venue\VenueStageRepositoryInterface;
use App\Domain\Venue\VenueStageRiserRepositoryInterface;

final class InMemoryVenueStageRiserRepository implements VenueStageRiserRepositoryInterface
{
    /** @var array<string, Riser> */
    private array $risers = [];

    /** Die Bühnen-Zuordnung zum Veranstaltungsort kommt wie in der Datenbank aus dem Bühnen-Bestand. */
    public function __construct(private readonly VenueStageRepositoryInterface $stages)
    {
    }

    public function save(Riser $riser): void
    {
        $this->risers[$riser->id] = $riser;
    }

    public function findById(string $id): ?Riser
    {
        return $this->risers[$id] ?? null;
    }

    public function findByVenueGroupedByStage(string $venueId): array
    {
        $grouped = [];
        foreach ($this->risers as $riser) {
            if ($this->stages->findById($riser->stageId)?->venueId === $venueId) {
                $grouped[$riser->stageId][] = $riser;
            }
        }

        return $grouped;
    }

    public function delete(string $id): void
    {
        unset($this->risers[$id]);
    }
}
