<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Calendar\CalendarKind;
use App\Domain\Calendar\CalendarRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateCalendarHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CalendarRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCalendar);

        $calendar = $this->repository->findById($command->id);
        if ($calendar === null) {
            throw new NotFoundException("Kalender \"{$command->id}\" wurde nicht gefunden.");
        }

        $name = trim((string) $command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für den Kalender angeben.']);
        }

        $calendar->name = $name;

        if ($calendar->kind === CalendarKind::Rental) {
            $categoryId = $this->nullIfEmpty($command->categoryId);
            $manufacturerId = $this->nullIfEmpty($command->manufacturerId);
            $warehouseId = $this->nullIfEmpty($command->warehouseId);
            $venueId = $this->nullIfEmpty($command->venueId);

            if ($categoryId === null && $manufacturerId === null && $warehouseId === null && $venueId === null) {
                throw new ValidationException(['filter' => 'Bitte mindestens ein Filterkriterium auswählen (sonst bitte den Veranstaltungskalender verwenden).']);
            }

            $calendar->categoryId = $categoryId;
            $calendar->manufacturerId = $manufacturerId;
            $calendar->warehouseId = $warehouseId;
            $calendar->venueId = $venueId;
        }
        // kind === Event: Filterfelder bleiben unverändert null (siehe Docblock CalendarKind).

        $calendar->updatedAt = new DateTimeImmutable();

        $this->repository->save($calendar);

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
