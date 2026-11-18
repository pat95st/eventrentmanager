<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\RentalSeries\RentalSeriesRepositoryInterface;

final class GetRentalSeriesContextHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalSeriesRepositoryInterface $series,
    ) {
    }

    public function handle(QueryInterface $query): ?RentalSeriesContext
    {
        assert($query instanceof GetRentalSeriesContext);
        $rental = $this->rentals->findById($query->rentalId);
        if ($rental === null || $rental->seriesId === null) {
            return null;
        }
        $series = $this->series->findById($rental->seriesId);
        if ($series === null) {
            return null;
        }
        $occurrences = $this->rentals->findBySeries($series->id);
        $position = 0;
        foreach ($occurrences as $i => $occurrence) {
            if ($occurrence->id === $rental->id) {
                $position = $i + 1;
                break;
            }
        }

        return new RentalSeriesContext(
            $series,
            $position,
            count($occurrences),
            $position > 1 ? $occurrences[$position - 2] : null,
            array_slice($occurrences, $position),
        );
    }
}
