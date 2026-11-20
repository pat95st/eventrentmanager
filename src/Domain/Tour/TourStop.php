<?php

declare(strict_types=1);

namespace App\Domain\Tour;

use DateTimeImmutable;

/**
 * Stop of a tour (migration 177): either an event at a venue (Stop) or a
 * stop between venues (Waypoint, e.g. loading at a warehouse or a hotel).
 */
final class TourStop
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public TourStopKind $kind,
        // stop
        public ?string $venueId = null,
        public ?string $locationText = null,
        public ?DateTimeImmutable $setupAt = null,
        public ?DateTimeImmutable $eventStartAt = null,
        public ?DateTimeImmutable $eventEndAt = null,
        public ?DateTimeImmutable $teardownEndAt = null,
        public ?string $contactName = null,
        public ?string $contactPhone = null,
        public ?string $note = null,
        // waypoint
        public ?string $afterStopId = null,
        public ?WaypointPurpose $purpose = null,
        public ?WaypointAddressType $addressType = null,
        public ?string $warehouseId = null,
        public ?string $savedAddressId = null,
        public ?string $label = null,
        public ?string $street = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?DateTimeImmutable $arriveAt = null,
        public ?DateTimeImmutable $departAt = null,
        public ?DateTimeImmutable $createdAt = null,
        // the drive to this point is not billed (migration 180)
        public bool $arrivalNotBilled = false,
        // kilometres of the drive to this point entered by hand (migration 182)
        public ?int $arrivalKmOverride = null,
    ) {
    }

    public function isStop(): bool
    {
        return $this->kind === TourStopKind::Stop;
    }

    public function isWaypoint(): bool
    {
        return $this->kind === TourStopKind::Waypoint;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Begin of the occupied period at this stop (setup or event start). */
    public function occupiedFrom(): ?DateTimeImmutable
    {
        return $this->isStop() ? ($this->setupAt ?? $this->eventStartAt) : ($this->arriveAt ?? $this->departAt);
    }

    /** End of the occupied period at this stop (teardown or event end). */
    public function occupiedUntil(): ?DateTimeImmutable
    {
        return $this->isStop() ? ($this->teardownEndAt ?? $this->eventEndAt ?? $this->eventStartAt) : ($this->departAt ?? $this->arriveAt);
    }

    public function title(): string
    {
        if ($this->isStop()) {
            return $this->locationText !== null && $this->locationText !== '' ? $this->locationText : 'Ohne Ort';
        }
        $what = $this->label ?? trim(($this->postalCode ?? '') . ' ' . ($this->city ?? ''));

        $prefix = match ($this->kind) {
            TourStopKind::Start => 'Start',
            TourStopKind::End => 'Ziel',
            default => $this->purpose?->label() ?? 'Zwischenstopp',
        };

        return $prefix . ($what !== '' ? ': ' . $what : '');
    }

    public function addressLine(): string
    {
        return trim(implode(', ', array_filter([$this->street, trim(($this->postalCode ?? '') . ' ' . ($this->city ?? ''))], static fn ($p) => $p !== null && trim($p) !== '')));
    }
}
