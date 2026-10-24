<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

use DateTimeImmutable;

/**
 * Ein über CalDAV abonnierbarer Kalender (siehe CalendarKind für die zwei
 * Ausprägungen). Bei kind = Rental sind eine oder mehrere der vier
 * Filter-Felder gesetzt (alle gesetzten werden UND-verknüpft; ein gesetztes
 * categoryId/manufacturerId matcht, sobald MINDESTENS EIN Artikel der
 * Vermietung passt — siehe RentalRepositoryInterface::searchForCalendar()).
 * Bei kind = Event bleiben alle vier immer null.
 */
final class Calendar
{
    public function __construct(
        public readonly string $id,
        public readonly CalendarKind $kind,
        public string $name,
        public bool $enabled,
        public ?string $categoryId,
        public ?string $manufacturerId,
        public ?string $warehouseId,
        public ?string $venueId,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
