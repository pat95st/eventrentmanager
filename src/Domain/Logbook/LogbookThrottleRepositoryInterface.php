<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

use DateTimeImmutable;

/** Counters of the public capture pages (migration 185, logbook_attempts). */
interface LogbookThrottleRepositoryInterface
{
    /** @return array{counter: int, windowStart: DateTimeImmutable, lockedUntil: ?DateTimeImmutable}|null */
    public function find(string $key): ?array;

    public function store(string $key, string $scope, int $counter, DateTimeImmutable $windowStart, ?DateTimeImmutable $lockedUntil): void;

    public function delete(string $key): void;

    /** Removes counters that were not touched since $before. */
    public function purge(DateTimeImmutable $before): void;
}
