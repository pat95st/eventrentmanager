<?php

declare(strict_types=1);

namespace App\Application\Tour;

use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Tour\TourMaterialResolver;
use App\Domain\Tour\TourPlan;
use App\Domain\Tour\TourRepositoryInterface;
use DateTimeImmutable;

/**
 * Keeps derived data of a tour up to date: the rental's dates follow its
 * stops, and rental_items.tour_extra_quantity follows the stop deviations.
 */
final class TourSync
{
    public function __construct(
        private readonly TourRepositoryInterface $tours,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalItemRepositoryInterface $items,
    ) {
    }

    public function plan(string $rentalId): TourPlan
    {
        return new TourPlan($this->tours->findStopsByRental($rentalId));
    }

    /** @return array<string, int> article id => base quantity */
    public function baseList(string $rentalId): array
    {
        $base = [];
        foreach ($this->items->findByRental($rentalId) as $item) {
            if ($item->quantity > 0) {
                $base[$item->articleId] = $item->quantity;
            }
        }

        return $base;
    }

    public function resolver(string $rentalId, ?TourPlan $plan = null): TourMaterialResolver
    {
        $plan ??= $this->plan($rentalId);

        return new TourMaterialResolver($this->baseList($rentalId), $this->tours->findItemsByRental($rentalId), $plan->stops());
    }

    public function sync(Rental $rental): void
    {
        $plan = $this->plan($rental->id);
        $this->tours->syncExtraQuantities($rental->id, $this->resolver($rental->id, $plan)->extraForIssue());

        $from = null;
        $to = null;
        foreach ($plan->stops() as $stop) {
            $start = $stop->occupiedFrom();
            $end = $stop->occupiedUntil();
            if ($start !== null && ($from === null || $start < $from)) {
                $from = $start;
            }
            if ($end !== null && ($to === null || $end > $to)) {
                $to = $end;
            }
        }
        if ($from === null || $to === null) {
            return;
        }
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        $changed = $rental->startDate->format('Y-m-d') !== $from->format('Y-m-d') || $rental->endDate->format('Y-m-d') !== $to->format('Y-m-d');
        $rental->startDate = $from;
        $rental->endDate = $to;

        // Deployment days of a tour: the days on which tour stops take place.
        $days = min(max(1, self::eventDays($plan->stops())), $rental->maxDeploymentDays());
        if ($days !== $rental->deploymentDays) {
            $rental->deploymentDays = $days;
            $changed = true;
        }
        if (!$changed) {
            return;
        }
        $rental->updatedAt = new DateTimeImmutable();
        $this->rentals->save($rental);
    }

    /**
     * Number of different days with an event at a tour stop (event start to
     * event end; setup and teardown days do not count, an end before 06:00
     * belongs to the previous day).
     *
     * @param \App\Domain\Tour\TourStop[] $stops
     */
    public static function eventDays(array $stops): int
    {
        $days = [];
        foreach ($stops as $stop) {
            if ($stop->eventStartAt === null) {
                continue;
            }
            $day = $stop->eventStartAt->setTime(0, 0);
            // An event ending shortly after midnight (e.g. 20:00 – 01:00) counts as one day.
            $end = ($stop->eventEndAt ?? $stop->eventStartAt)->modify('-6 hours')->setTime(0, 0);
            for ($guard = 0; $day <= $end && $guard < 366; $day = $day->modify('+1 day'), $guard++) {
                $days[$day->format('Y-m-d')] = true;
            }
        }

        return count($days);
    }
}
