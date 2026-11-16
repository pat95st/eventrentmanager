<?php

declare(strict_types=1);

namespace App\Application\Display;

use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use DateTimeImmutable;

/**
 * Selectable assignments for the display settings page (migration 168):
 * events with minute programs from a week ago until four months ahead, plus
 * the events currently assigned to a device (even if outside that window).
 */
final class DisplayTargets
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly MinuteProgramRepositoryInterface $programs,
    ) {
    }

    /**
     * @param list<string> $assignedRentalIds
     *
     * @return list<array{rental: Rental, programs: MinuteProgram[]}> sorted by event start
     */
    public function list(array $assignedRentalIds = [], ?DateTimeImmutable $now = null): array
    {
        $today = ($now ?? new DateTimeImmutable())->setTime(0, 0);
        $rentals = [];
        foreach ($this->rentals->findOverlapping($today->modify('-7 days'), $today->modify('+120 days')) as $rental) {
            $rentals[$rental->id] = $rental;
        }
        foreach (array_unique($assignedRentalIds) as $id) {
            if (!isset($rentals[$id]) && ($rental = $this->rentals->findById($id)) !== null) {
                $rentals[$id] = $rental;
            }
        }

        $result = [];
        foreach ($rentals as $rental) {
            $programs = $this->programs->findByRental($rental->id);
            if ($programs !== [] || in_array($rental->id, $assignedRentalIds, true)) {
                $result[] = ['rental' => $rental, 'programs' => $programs];
            }
        }
        usort($result, static fn (array $a, array $b): int => [$a['rental']->startDate, $a['rental']->eventName] <=> [$b['rental']->startDate, $b['rental']->eventName]);

        return $result;
    }
}
