<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Calendar\CalendarAccessRepositoryInterface;
use App\Domain\Calendar\CalendarRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\UserRepositoryInterface;

final class ListCalendarsForUserHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly CalendarRepositoryInterface $calendars,
        private readonly CalendarAccessRepositoryInterface $access,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /** @return \App\Domain\Calendar\Calendar[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListCalendarsForUser);

        $user = $this->users->findById($query->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer \"{$query->userId}\" wurde nicht gefunden.");
        }

        $isAdministrator = $user->isAdministrator();

        return array_values(array_filter(
            $this->calendars->findAll(),
            fn ($calendar) => $calendar->enabled
                && ($isAdministrator || $this->access->hasAccess($calendar->id, $user->id))
        ));
    }
}
