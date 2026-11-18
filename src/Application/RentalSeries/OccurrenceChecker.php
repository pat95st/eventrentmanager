<?php

declare(strict_types=1);

namespace App\Application\RentalSeries;

use App\Application\Rental\RentalScheduleRules;
use App\Domain\Rental\Rental;
use DateTimeImmutable;

/**
 * Warnings for a planned occurrence: company holidays, exclusive events and
 * material that is short on that date.
 */
final class OccurrenceChecker
{
    public function __construct(
        private readonly ?MaterialConflictFinder $conflicts = null,
        private readonly ?RentalScheduleRules $schedule = null,
    ) {
    }

    /**
     * @param array<string, int> $material
     * @return array{warnings: string[], conflicts: MaterialConflict[], blocking: ?string, holiday: bool}
     */
    public function check(Rental $candidate, array $material, array $excludeRentalIds = []): array
    {
        $warnings = [];
        $holiday = false;
        $blocking = null;
        if ($this->schedule !== null) {
            $holidays = $this->schedule->holidaysDuring($candidate->startDate, $candidate->endDate);
            if ($holidays !== []) {
                $holiday = true;
                $warnings[] = 'Betriebsurlaub: ' . implode(', ', array_map(static fn ($h) => $h->label(), $holidays));
            }
            $blocking = $this->schedule->blockingError($candidate);
            if ($blocking !== null) {
                $warnings[] = $blocking;
            }
        }
        $found = $this->conflicts?->find($material, $candidate->occupiedFrom(), $candidate->occupiedUntil(), $excludeRentalIds) ?? [];

        return ['warnings' => $warnings, 'conflicts' => $found, 'blocking' => $blocking, 'holiday' => $holiday];
    }

    /** Unsaved copy of $source moved to $start (duration and logistics offsets kept), for checks. */
    public static function candidate(Rental $source, DateTimeImmutable $start, ?DateTimeImmutable $end = null): Rental
    {
        $offset = RentalCopier::dayOffset($source->startDate, $start);
        $shift = static fn (?DateTimeImmutable $d): ?DateTimeImmutable => $d?->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
        $candidate = new Rental(
            id: 'candidate',
            customerId: $source->customerId,
            startDate: $start->setTime(0, 0),
            endDate: ($end ?? $shift($source->endDate))->setTime(0, 0),
            eventName: $source->eventName,
            eventLocation: $source->eventLocation,
            venueId: $source->venueId,
            deploymentDays: $source->deploymentDays,
            createdAt: $source->createdAt,
            updatedAt: $source->updatedAt,
            pickupAt: $shift($source->pickupAt),
            returnAt: $shift($source->returnAt),
            blocksOtherRentals: $source->blocksOtherRentals,
        );

        return $candidate;
    }
}
