<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Calendar\CalendarAccessRepositoryInterface;
use App\Domain\Calendar\CalendarRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class SetCalendarAccessHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CalendarRepositoryInterface $calendars,
        private readonly CalendarAccessRepositoryInterface $access,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SetCalendarAccess);

        if ($this->calendars->findById($command->calendarId) === null) {
            throw new NotFoundException("Kalender \"{$command->calendarId}\" wurde nicht gefunden.");
        }

        $this->access->replaceForCalendar($command->calendarId, $command->userIds);

        return null;
    }
}
