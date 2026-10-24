<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\CalendarKind;
use App\Domain\Calendar\CalendarRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateCalendarHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CalendarRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CreateCalendar);

        $name = trim((string) $command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für den Kalender angeben.']);
        }

        $categoryId = $this->nullIfEmpty($command->categoryId);
        $manufacturerId = $this->nullIfEmpty($command->manufacturerId);
        $warehouseId = $this->nullIfEmpty($command->warehouseId);
        $venueId = $this->nullIfEmpty($command->venueId);

        if ($categoryId === null && $manufacturerId === null && $warehouseId === null && $venueId === null) {
            throw new ValidationException(['filter' => 'Bitte mindestens ein Filterkriterium auswählen (sonst bitte den Veranstaltungskalender verwenden).']);
        }

        $now = new DateTimeImmutable();

        $calendar = new Calendar(
            id: Uuid::v4(),
            kind: CalendarKind::Rental,
            name: $name,
            enabled: false,
            categoryId: $categoryId,
            manufacturerId: $manufacturerId,
            warehouseId: $warehouseId,
            venueId: $venueId,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repository->save($calendar);

        return $calendar->id;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
