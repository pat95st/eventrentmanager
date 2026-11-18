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
        if ($rental->startDate->format('Y-m-d') === $from->format('Y-m-d') && $rental->endDate->format('Y-m-d') === $to->format('Y-m-d')) {
            return;
        }
        $rental->startDate = $from;
        $rental->endDate = $to;
        $rental->deploymentDays = min(max(1, $rental->deploymentDays), $rental->maxDeploymentDays());
        $rental->updatedAt = new DateTimeImmutable();
        $this->rentals->save($rental);
    }
}
