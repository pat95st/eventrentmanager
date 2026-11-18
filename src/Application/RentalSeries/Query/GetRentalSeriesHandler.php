<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\RentalSeries\MaterialConflictFinder;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Customer\CustomerRepositoryInterface;
use DateTimeImmutable;

final class GetRentalSeriesHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RentalCopier $copier,
        private readonly ?MaterialConflictFinder $conflicts = null,
        private readonly ?CustomerRepositoryInterface $customers = null,
    ) {
    }

    public function handle(QueryInterface $query): RentalSeriesOverview
    {
        assert($query instanceof GetRentalSeries);
        $series = $this->support->series($query->seriesId);
        $today = new DateTimeImmutable('today');
        $customerNames = [];
        $rows = [];
        foreach ($this->support->occurrences($series->id) as $rental) {
            $customerNames[$rental->customerId] ??= $this->customers?->findById($rental->customerId)?->displayName() ?? '';
            $started = $this->copier->isStarted($rental);
            $rows[] = [
                'rental' => $rental,
                'customer' => $customerNames[$rental->customerId],
                'started' => $started,
                // Past occurrences do not need warnings anymore.
                'conflicts' => !$started && $rental->occupiedUntil() >= $today ? ($this->conflicts?->findForRental($rental) ?? []) : [],
            ];
        }

        return new RentalSeriesOverview($series, $rows);
    }
}
