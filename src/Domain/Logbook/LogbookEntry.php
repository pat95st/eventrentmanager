<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

use DateTimeImmutable;

/**
 * One trip of the driver's logbook (migration 184). distanceKm is the whole
 * trip (doubled for round trips); with both odometer readings it equals
 * odometerEnd - odometerStart. A driver is either a user (driverUserId) or an
 * external person (driverName, isExternal).
 */
final class LogbookEntry
{
    public function __construct(
        public readonly string $id,
        public DateTimeImmutable $tripDate,
        public ?string $departTime, // "HH:MM"
        public ?string $arriveTime, // "HH:MM"
        public ?string $driverUserId,
        public ?string $driverName,
        public ?string $driverPhone,
        public bool $isExternal,
        public ?string $vehicleId,
        public ?string $otherVehicleLabel,
        public ?string $trailerId,
        public LogbookCategory $category,
        public ?string $rentalId,
        public ?string $tourStopId,
        public ?string $purpose,
        public ?LogbookLocation $from,
        public ?LogbookLocation $to,
        public bool $roundTrip,
        public int $distanceKm,
        public ?int $odometerStart,
        public ?int $odometerEnd,
        public bool $notBilled,
        public LogbookStatus $status,
        public bool $odometerCheckBypassed,
        public ?string $createdByUserId,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $deletedAt = null,
        public ?string $deletedByUserId = null,
    ) {
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /** Recorded by or for this user (own trip)? */
    public function belongsTo(?string $userId): bool
    {
        return $userId !== null && !$this->isExternal
            && ($this->driverUserId === $userId || $this->createdByUserId === $userId);
    }

    /** Counts for travel cost billing of its event. */
    public function isBillable(): bool
    {
        return !$this->isDeleted() && !$this->notBilled && $this->status === LogbookStatus::Approved
            && $this->category === LogbookCategory::Event;
    }

    /** Field values for the audit trail (logbook_entry_changes). */
    public function auditValues(): array
    {
        return [
            'tripDate' => $this->tripDate->format('Y-m-d'),
            'departTime' => $this->departTime,
            'arriveTime' => $this->arriveTime,
            'driverUserId' => $this->driverUserId,
            'driverName' => $this->driverName,
            'driverPhone' => $this->driverPhone,
            'vehicleId' => $this->vehicleId,
            'otherVehicleLabel' => $this->otherVehicleLabel,
            'trailerId' => $this->trailerId,
            'category' => $this->category->value,
            'rentalId' => $this->rentalId,
            'tourStopId' => $this->tourStopId,
            'purpose' => $this->purpose,
            'from' => $this->from?->display(),
            'to' => $this->to?->display(),
            'roundTrip' => $this->roundTrip,
            'distanceKm' => $this->distanceKm,
            'odometerStart' => $this->odometerStart,
            'odometerEnd' => $this->odometerEnd,
            'notBilled' => $this->notBilled,
            'status' => $this->status->value,
        ];
    }
}
