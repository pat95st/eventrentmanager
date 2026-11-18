<?php

declare(strict_types=1);

namespace App\Application\RentalSeries;

use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\RentalSeries\RentalSeries;
use App\Domain\RentalSeries\RentalSeriesRepositoryInterface;
use DateTimeImmutable;

/** Small helpers shared by the series handlers. */
final class SeriesSupport
{
    public function __construct(
        private readonly RentalSeriesRepositoryInterface $series,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    public function series(string $id): RentalSeries
    {
        return $this->series->findById($id) ?? throw new NotFoundException('Die Serie wurde nicht gefunden.');
    }

    public function rental(string $id): Rental
    {
        return $this->rentals->findById($id) ?? throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
    }

    /** @return Rental[] sorted by start date */
    public function occurrences(string $seriesId): array
    {
        return $this->rentals->findBySeries($seriesId);
    }

    /** Latest occurrence starting before $date (the "previous" one), or null. */
    public function previousOf(array $occurrences, DateTimeImmutable $date, ?string $excludeId = null): ?Rental
    {
        $previous = null;
        foreach ($occurrences as $occurrence) {
            if ($occurrence->id === $excludeId) {
                continue;
            }
            if ($occurrence->startDate->format('Y-m-d') < $date->format('Y-m-d')) {
                $previous = $occurrence;
            }
        }

        return $previous;
    }

    /**
     * Renumbers series_index by start date; with a template containing
     * {nr}, names of occurrences that are not yet started follow.
     *
     * @param callable(Rental): bool $isStarted
     */
    public function renumber(RentalSeries $series, callable $isStarted): void
    {
        $usesNumber = $series->nameTemplate !== null && str_contains($series->nameTemplate, '{nr}');
        foreach ($this->rentals->findBySeries($series->id) as $i => $rental) {
            $index = $i + 1;
            if ($rental->seriesIndex === $index) {
                continue;
            }
            $rental->seriesIndex = $index;
            if ($usesNumber && !$isStarted($rental)) {
                $rental->eventName = $series->nameFor($rental->eventName, $rental->startDate, $index);
            }
            $this->rentals->save($rental);
        }
    }

    public static function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
