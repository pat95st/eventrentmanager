<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Calendar\CalendarAccessRepositoryInterface;

final class ListUserIdsForCalendarHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CalendarAccessRepositoryInterface $repository)
    {
    }

    /** @return string[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListUserIdsForCalendar);

        return $this->repository->userIdsForCalendar($query->calendarId);
    }
}
