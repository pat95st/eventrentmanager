<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Calendar\CalendarKind;
use App\Domain\Calendar\CalendarRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;

final class DeleteCalendarHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CalendarRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCalendar);

        $calendar = $this->repository->findById($command->id);
        if ($calendar === null) {
            throw new NotFoundException("Kalender \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($calendar->kind === CalendarKind::Event) {
            throw new ValidationException(['id' => 'Der Veranstaltungskalender ist fest eingebaut und kann nicht gelöscht werden — er lässt sich aber deaktivieren.']);
        }

        $this->repository->delete($calendar->id);

        return null;
    }
}
