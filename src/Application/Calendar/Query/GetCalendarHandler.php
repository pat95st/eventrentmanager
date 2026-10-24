<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\CalendarRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class GetCalendarHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CalendarRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): Calendar
    {
        assert($query instanceof GetCalendar);

        $calendar = $this->repository->findById($query->id);
        if ($calendar === null) {
            throw new NotFoundException("Kalender \"{$query->id}\" wurde nicht gefunden.");
        }

        return $calendar;
    }
}
