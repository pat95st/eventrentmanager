<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

interface LogbookEntryRepositoryInterface
{
    public function save(LogbookEntry $entry): void;

    public function findById(string $id): ?LogbookEntry;

    /** @return LogbookEntry[] newest trip first */
    public function search(LogbookFilter $filter, ?int $limit = null, int $offset = 0): array;

    public function count(LogbookFilter $filter): int;

    /** @return array{count: int, km: int, byCategory: array<string, int>} */
    public function totals(LogbookFilter $filter): array;

    /** Trips (also deleted ones) that use the vehicle as vehicle or trailer. */
    public function countByVehicle(string $vehicleId): int;

    /**
     * Highest odometer reading at arrival of the vehicle's trips that are not
     * deleted (pending and rejected trips count, migration 185); null = none.
     */
    public function lastOdometerReading(string $vehicleId): ?int;

    public function addChange(LogbookEntryChange $change): void;

    /** @return LogbookEntryChange[] oldest first */
    public function findChanges(string $entryId): array;
}
