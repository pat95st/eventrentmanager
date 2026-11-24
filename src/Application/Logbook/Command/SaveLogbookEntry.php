<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandInterface;

/**
 * Records a new trip (entryId null) or changes one (migration 184), see
 * SaveLogbookEntryHandler. Values come straight from the form.
 */
final class SaveLogbookEntry implements CommandInterface
{
    public function __construct(
        public readonly ?string $entryId,
        // Logged-in user; null for external drivers (externalVehicleId set).
        public readonly ?string $actorUserId,
        public readonly string $tripDate,
        public readonly ?string $departTime = null,
        public readonly ?string $arriveTime = null,
        // Driver: a user id, or "external" with driverName (logbook managers only).
        public readonly ?string $driverUserId = null,
        public readonly ?string $driverName = null,
        public readonly ?string $driverPhone = null,
        // Vehicle id, or "other" with otherVehicleLabel.
        public readonly ?string $vehicleId = null,
        public readonly ?string $otherVehicleLabel = null,
        public readonly ?string $trailerId = null,
        public readonly string $category = 'event',
        public readonly ?string $rentalId = null,
        public readonly ?string $tourStopId = null,
        public readonly ?string $purpose = null,
        // Start/destination as "type:refId", free text for "custom:".
        public readonly ?string $from = null,
        public readonly ?string $fromText = null,
        public readonly bool $saveFromAddress = false,
        public readonly ?string $to = null,
        public readonly ?string $toText = null,
        public readonly bool $saveToAddress = false,
        public readonly bool $roundTrip = false,
        // One-way distance for round trips, otherwise the whole trip.
        public readonly ?string $distanceKm = null,
        public readonly ?string $odometerStart = null,
        public readonly ?string $odometerEnd = null,
        // Only logbook managers; null keeps the current value.
        public readonly ?bool $notBilled = null,
        // Migration 185: external driver via the QR code of this vehicle (actorUserId null).
        public readonly ?string $externalVehicleId = null,
        // Migration 185: logbook managers may skip the odometer check (logged).
        public readonly bool $bypassOdometerCheck = false,
    ) {
    }
}
