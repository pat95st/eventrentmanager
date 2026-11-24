<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Logbook\LogbookEntry;
use App\Domain\Logbook\LogbookEntryChange;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookFilter;

/** In-memory test double of LogbookEntryRepositoryInterface (filters only deleted trips). */
final class InMemoryLogbookEntryRepository implements LogbookEntryRepositoryInterface
{
    /** @var array<string, LogbookEntry> */
    public array $entries = [];
    /** @var LogbookEntryChange[] */
    public array $changes = [];

    public function save(LogbookEntry $entry): void
    {
        $this->entries[$entry->id] = clone $entry;
    }

    public function findById(string $id): ?LogbookEntry
    {
        return isset($this->entries[$id]) ? clone $this->entries[$id] : null;
    }

    public function search(LogbookFilter $filter, ?int $limit = null, int $offset = 0): array
    {
        return array_values(array_filter($this->entries, static fn (LogbookEntry $e) => $filter->includeDeleted || !$e->isDeleted()));
    }

    public function count(LogbookFilter $filter): int
    {
        return count($this->search($filter));
    }

    public function totals(LogbookFilter $filter): array
    {
        $km = array_sum(array_map(static fn (LogbookEntry $e) => $e->distanceKm, $this->search($filter)));

        return ['count' => $this->count($filter), 'km' => $km, 'byCategory' => []];
    }

    public function countByVehicle(string $vehicleId): int
    {
        return count(array_filter($this->entries, static fn (LogbookEntry $e) => $e->vehicleId === $vehicleId || $e->trailerId === $vehicleId));
    }

    public function lastOdometerReading(string $vehicleId): ?int
    {
        $readings = array_map(
            static fn (LogbookEntry $e) => $e->odometerEnd,
            array_filter($this->entries, static fn (LogbookEntry $e) => $e->vehicleId === $vehicleId && !$e->isDeleted() && $e->odometerEnd !== null),
        );

        return $readings === [] ? null : max($readings);
    }

    public function addChange(LogbookEntryChange $change): void
    {
        $this->changes[] = $change;
    }

    public function findChanges(string $entryId): array
    {
        return array_values(array_filter($this->changes, static fn (LogbookEntryChange $c) => $c->entryId === $entryId));
    }
}
