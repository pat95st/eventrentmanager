<?php

declare(strict_types=1);

namespace App\Domain\PlannedTrip;

use App\Domain\Logbook\LogbookLocation;
use DateTimeImmutable;

/**
 * Planned trip of an event that is not a tour (migration 186). Start and
 * destination are snapshots (LogbookLocation). The planned kilometres are
 * the ones entered by hand, otherwise the calculated driving distance.
 * defaultKind marks an unchanged default trip that follows the event.
 */
final class PlannedTrip
{
    public const OUTBOUND = 'outbound';
    public const RETURN = 'return';

    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public int $sortOrder,
        public ?DateTimeImmutable $tripDate,
        public ?LogbookLocation $from,
        public ?LogbookLocation $to,
        public ?int $distanceKm,
        public ?int $kmOverride,
        public ?string $defaultKind,
        public ?string $defaultSignature,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** Planned kilometres; null = neither calculated nor entered. */
    public function kilometers(): ?int
    {
        return $this->kmOverride ?? $this->distanceKm;
    }

    public function isDefault(): bool
    {
        return $this->defaultKind !== null;
    }

    /** Edited default trip (see PlannedTripService::restoreDefaults()). */
    public function wasDefault(): bool
    {
        return str_starts_with((string) $this->defaultSignature, 'origin:');
    }

    public function defaultLabel(): ?string
    {
        return match ($this->defaultKind) {
            self::OUTBOUND => 'Hinfahrt',
            self::RETURN => 'Rückfahrt',
            default => null,
        };
    }
}
