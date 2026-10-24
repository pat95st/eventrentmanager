<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Calendar\CalendarRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use DateTimeImmutable;

final class SetCalendarEnabledHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CalendarRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SetCalendarEnabled);

        $calendar = $this->repository->findById($command->id);
        if ($calendar === null) {
            throw new NotFoundException("Kalender \"{$command->id}\" wurde nicht gefunden.");
        }

        $calendar->enabled = $command->enabled;
        $calendar->updatedAt = new DateTimeImmutable();

        $this->repository->save($calendar);

        return null;
    }
}
