<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use DateTimeImmutable;

/** In-Memory-Test-Double für RentalRepositoryInterface (siehe tests/README.md); Such-/Erinnerungsabfragen liefern nichts. */
final class InMemoryRentalRepository implements RentalRepositoryInterface
{
    /** @var array<string, Rental> */
    private array $rentals = [];

    public function save(Rental $rental): void
    {
        $this->rentals[$rental->id] = $rental;
    }

    public function findById(string $id): ?Rental
    {
        return $this->rentals[$id] ?? null;
    }

    public function search(?string $searchTerm, ?string $customerId, int $limit, int $offset): array
    {
        return [];
    }

    public function countSearch(?string $searchTerm, ?string $customerId): int
    {
        return 0;
    }

    public function findOverlapping(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [];
    }

    public function findByVenue(string $venueId): array
    {
        return [];
    }

    public function delete(string $id): void
    {
        unset($this->rentals[$id]);
    }

    public function searchForCalendar(
        array $categoryIds,
        ?string $manufacturerId,
        ?string $warehouseId,
        ?string $venueId,
        ?DateTimeImmutable $rangeStart,
        ?DateTimeImmutable $rangeEnd,
    ): array {
        return [];
    }

    public function findPickupsDueForReminder(DateTimeImmutable $now, int $leadHours): array
    {
        return [];
    }

    public function findReturnsDueForReminder(DateTimeImmutable $now, int $leadHours): array
    {
        return [];
    }

    public function findChecklistsDueForReminder(DateTimeImmutable $now, int $leadHours): array
    {
        return [];
    }
}
