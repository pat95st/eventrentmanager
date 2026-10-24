<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Calendar\CalendarRepositoryInterface;

final class ListCalendarsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CalendarRepositoryInterface $repository)
    {
    }

    /** @return \App\Domain\Calendar\Calendar[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListCalendars);

        return $this->repository->findAll();
    }
}
