<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Calendar\CalendarRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalRepositoryInterface;

final class ListRentalsForCalendarHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly CalendarRepositoryInterface $calendars,
        private readonly RentalRepositoryInterface $rentals,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    /** @return \App\Domain\Rental\Rental[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRentalsForCalendar);

        $calendar = $this->calendars->findById($query->calendarId);
        if ($calendar === null) {
            throw new NotFoundException("Kalender \"{$query->calendarId}\" wurde nicht gefunden.");
        }

        // Kategorie-Filter schließt Unterkategorien mit ein — dieselbe Logik
        // wie bei der Artikel-Übersicht (siehe ListArticlesHandler).
        $categoryIds = [];
        if ($calendar->categoryId !== null) {
            $categoryIds = array_merge([$calendar->categoryId], $this->categories->findDescendantIds($calendar->categoryId));
        }

        return $this->rentals->searchForCalendar(
            $categoryIds,
            $calendar->manufacturerId,
            $calendar->warehouseId,
            $calendar->venueId,
            $query->rangeStart,
            $query->rangeEnd,
        );
    }
}
