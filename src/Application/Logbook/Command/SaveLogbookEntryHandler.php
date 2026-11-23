<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Logbook\LogbookExternalEvents;
use App\Application\Logbook\LogbookLocationResolver;
use App\Domain\Exception\ForbiddenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookAccess;
use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookEntry;
use App\Domain\Logbook\LogbookEntryChange;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookLocationType;
use App\Domain\Logbook\LogbookOdometerChain;
use App\Domain\Logbook\LogbookSettingsRepositoryInterface;
use App\Domain\Logbook\LogbookStatus;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Records or changes a trip of the driver's logbook (migration 184).
 *
 * Rules:
 * - Drivers ("Fahrer") record trips for themselves; logbook managers may
 *   choose any user or an external person as driver.
 * - Private vehicles: only their owner and logbook managers (LogbookAccess).
 * - The vehicle must be a car or "other vehicle" with a label; the optional
 *   trailer must be a trailer.
 * - Event trips need an event (tour stop optional, must belong to it),
 *   business trips a purpose; both need start and destination. Private
 *   trips need neither.
 * - With both odometer readings the distance is end - start; otherwise the
 *   distance is required and doubled for round trips. A vehicle with odometer
 *   check requires both readings.
 * - "Nicht abrechnen" is only changed by logbook managers.
 * - Every creation and change is written to the audit trail.
 * - External drivers (QR code, migration 185) record trips with the QR
 *   vehicle only, need their name, may only choose events the vehicle is
 *   planned for, the warehouses, company, event locations or a free
 *   address, and private trips only if the vehicle allows them. Their trips
 *   are pending if the vehicle requires approval.
 * - New trips of a vehicle with odometer check must start at the last
 *   recorded reading (within the tolerance); logbook managers may skip the
 *   check, which is marked on the trip.
 */
final class SaveLogbookEntryHandler implements CommandHandlerInterface
{
    public const MAX_DISTANCE_KM = 20000;
    public const ODOMETER_MISMATCH = 'Der Kilometerstand bei Abfahrt passt nicht zum zuletzt erfassten Kilometerstand dieses Fahrzeugs.';

    public function __construct(
        private readonly LogbookEntryRepositoryInterface $entries,
        private readonly LogbookSettingsRepositoryInterface $settings,
        private readonly UserRepositoryInterface $users,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly RentalRepositoryInterface $rentals,
        private readonly TourRepositoryInterface $tours,
        private readonly LogbookLocationResolver $locations,
        private readonly ?LogbookExternalEvents $externalEvents = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof SaveLogbookEntry);

        $external = $command->externalVehicleId !== null;
        $actor = null;
        if ($external) {
            if ($command->entryId !== null) {
                throw new ForbiddenException('Externe Fahrer können Fahrten nicht ändern.');
            }
        } else {
            $actor = $command->actorUserId !== null ? $this->users->findById($command->actorUserId) : null;
            if ($actor === null || !$actor->isActive || !LogbookAccess::canCapture($actor)) {
                throw new ForbiddenException('Du darfst keine Fahrten erfassen.');
            }
        }
        $manager = LogbookAccess::canManage($actor);

        $existing = null;
        if ($command->entryId !== null) {
            $existing = $this->entries->findById($command->entryId);
            if ($existing === null || $existing->isDeleted()) {
                throw new NotFoundException('Die Fahrt wurde nicht gefunden.');
            }
            if (!LogbookAccess::canEdit($actor, $existing, $this->settings->get())) {
                throw new ForbiddenException('Diese Fahrt kann nicht mehr geändert werden.');
            }
        }

        $errors = [];

        $tripDate = $this->parseDate($command->tripDate, $errors);
        $departTime = $this->parseTime($command->departTime, 'departTime', $errors);
        $arriveTime = $this->parseTime($command->arriveTime, 'arriveTime', $errors);

        if ($external) {
            $driverName = $this->text($command->driverName, 150);
            if ($driverName === null) {
                $errors['driverName'] = 'Bitte deinen Namen angeben.';
            }
            [$driverUserId, $driverPhone, $isExternal] = [null, $this->text($command->driverPhone, 50), true];
            $vehicle = $this->vehicles->findById((string) $command->externalVehicleId);
            if ($vehicle === null || !$vehicle->logbookQrEnabled || !$vehicle->logbookAnonymousAllowed || $vehicle->category !== VehicleCategory::Auto) {
                throw new ForbiddenException('Für dieses Fahrzeug können keine Fahrten ohne Anmeldung erfasst werden.');
            }
            $otherVehicleLabel = null;
        } else {
            [$driverUserId, $driverName, $driverPhone, $isExternal] = $this->driver($command, $actor, $manager, $existing, $errors);
            [$vehicle, $otherVehicleLabel] = $this->vehicle($command, $actor, $existing, $errors);
        }
        $trailer = $this->trailer($command->trailerId, $actor, $existing, $errors);

        $category = LogbookCategory::tryFrom($command->category);
        $allowed = LogbookCategory::selectable();
        if ($external && !$vehicle->logbookAnonymousPrivateAllowed) {
            $allowed = [LogbookCategory::Event, LogbookCategory::Business];
        }
        if ($existing?->category === LogbookCategory::Unclear) {
            $allowed[] = LogbookCategory::Unclear;
        }
        if ($category === null || !in_array($category, $allowed, true)) {
            $errors['category'] = 'Bitte eine Kategorie wählen.';
        }

        $rentalId = null;
        $tourStopId = null;
        if ($category === LogbookCategory::Event) {
            $rental = $command->rentalId !== null && $command->rentalId !== '' ? $this->rentals->findById($command->rentalId) : null;
            if ($rental !== null && $external && ($tripDate === null || $this->externalEvents === null || !$this->externalEvents->allows($vehicle->id, $tripDate, $rental->id))) {
                $rental = null;
            }
            if ($rental === null) {
                $errors['rentalId'] = 'Bitte die Veranstaltung wählen.';
            } else {
                $rentalId = $rental->id;
                if ($command->tourStopId !== null && $command->tourStopId !== '') {
                    $stop = $this->tours->findStop($command->tourStopId);
                    if ($stop === null || $stop->rentalId !== $rental->id) {
                        $errors['tourStopId'] = 'Der Tourstop gehört nicht zu dieser Veranstaltung.';
                    } else {
                        $tourStopId = $stop->id;
                    }
                }
            }
        }

        $purpose = $this->text($command->purpose, 500);
        if ($category === LogbookCategory::Business && $purpose === null) {
            $errors['purpose'] = 'Bitte den Zweck der Fahrt angeben.';
        }

        $from = $to = null;
        $externalTypes = [LogbookLocationType::Warehouse->value, LogbookLocationType::EventVenue->value, LogbookLocationType::TourStop->value, LogbookLocationType::Company->value, LogbookLocationType::Custom->value];
        foreach (['from' => $command->from, 'to' => $command->to] as $field => $choice) {
            if ($external && $choice !== null && $choice !== '' && !in_array(explode(':', $choice, 2)[0], $externalTypes, true)) {
                $errors[$field] = 'Bitte einen gültigen Ort wählen.';
            }
        }
        $saveAddresses = !$external && !$this->hasErrorsBeyondLocations($errors);
        try {
            $from = $this->locations->resolve($command->from, $command->fromText, $rentalId, 'from', $command->saveFromAddress && $saveAddresses);
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }
        try {
            $to = $this->locations->resolve($command->to, $command->toText, $rentalId, 'to', $command->saveToAddress && $saveAddresses);
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }
        if ($category !== null && $category !== LogbookCategory::Private && $category !== LogbookCategory::Unclear) {
            if ($from === null && !isset($errors['from'])) {
                $errors['from'] = 'Bitte den Startort wählen.';
            }
            if ($to === null && !isset($errors['to'])) {
                $errors['to'] = 'Bitte das Ziel wählen.';
            }
        }

        $odometerStart = $this->parseKm($command->odometerStart, 'odometerStart', $errors);
        $odometerEnd = $this->parseKm($command->odometerEnd, 'odometerEnd', $errors);
        if ($vehicle?->logbookOdometerCheck) {
            if ($odometerStart === null && !isset($errors['odometerStart'])) {
                $errors['odometerStart'] = 'Für dieses Fahrzeug ist der Kilometerstand bei Abfahrt Pflicht.';
            }
            if ($odometerEnd === null && !isset($errors['odometerEnd'])) {
                $errors['odometerEnd'] = 'Für dieses Fahrzeug ist der Kilometerstand bei Ankunft Pflicht.';
            }
        }
        if (($odometerStart === null) !== ($odometerEnd === null) && !isset($errors['odometerStart'], $errors['odometerEnd'])) {
            $errors[$odometerStart === null ? 'odometerStart' : 'odometerEnd'] = 'Bitte beide Kilometerstände angeben oder beide leer lassen.';
        }
        $bypassed = $existing?->odometerCheckBypassed ?? false;
        if ($vehicle?->logbookOdometerCheck && $existing === null && $odometerStart !== null && !isset($errors['odometerStart'])) {
            if (!(new LogbookOdometerChain($this->entries))->matches($vehicle, $odometerStart)) {
                if ($manager && $command->bypassOdometerCheck) {
                    $bypassed = true;
                } else {
                    $errors['odometerStart'] = self::ODOMETER_MISMATCH;
                }
            }
        }
        $distance = null;
        if ($odometerStart !== null && $odometerEnd !== null) {
            if ($odometerEnd < $odometerStart) {
                $errors['odometerEnd'] = 'Der Kilometerstand bei Ankunft muss mindestens so hoch sein wie bei Abfahrt.';
            } elseif ($odometerEnd - $odometerStart > self::MAX_DISTANCE_KM) {
                $errors['odometerEnd'] = 'Die Strecke ist unplausibel lang.';
            } else {
                $distance = $odometerEnd - $odometerStart;
            }
        } else {
            $entered = $this->parseKm($command->distanceKm, 'distanceKm', $errors);
            if ($entered === null && !isset($errors['distanceKm'])) {
                $errors['distanceKm'] = 'Bitte die gefahrenen Kilometer angeben.';
            } elseif ($entered !== null) {
                $distance = $command->roundTrip ? $entered * 2 : $entered;
                if ($distance > self::MAX_DISTANCE_KM) {
                    $errors['distanceKm'] = 'Die Strecke ist unplausibel lang.';
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        assert($tripDate !== null && $category !== null && $distance !== null);

        $now = new DateTimeImmutable();
        $before = $existing?->auditValues();
        $entry = $existing ?? new LogbookEntry(
            id: Uuid::v4(),
            tripDate: $tripDate,
            departTime: null,
            arriveTime: null,
            driverUserId: null,
            driverName: null,
            driverPhone: null,
            isExternal: false,
            vehicleId: null,
            otherVehicleLabel: null,
            trailerId: null,
            category: $category,
            rentalId: null,
            tourStopId: null,
            purpose: null,
            from: null,
            to: null,
            roundTrip: false,
            distanceKm: 0,
            odometerStart: null,
            odometerEnd: null,
            notBilled: false,
            status: LogbookStatus::Approved,
            odometerCheckBypassed: false,
            createdByUserId: $actor?->id,
            createdAt: $now,
            updatedAt: $now,
        );

        $entry->tripDate = $tripDate;
        $entry->departTime = $departTime;
        $entry->arriveTime = $arriveTime;
        $entry->driverUserId = $driverUserId;
        $entry->driverName = $driverName;
        $entry->driverPhone = $driverPhone;
        $entry->isExternal = $isExternal;
        $entry->vehicleId = $vehicle?->id;
        $entry->otherVehicleLabel = $otherVehicleLabel;
        $entry->trailerId = $trailer?->id;
        $entry->category = $category;
        $entry->rentalId = $rentalId;
        $entry->tourStopId = $tourStopId;
        $entry->purpose = $purpose;
        $entry->from = $from;
        $entry->to = $to;
        $entry->roundTrip = $command->roundTrip;
        $entry->distanceKm = $distance;
        $entry->odometerStart = $odometerStart;
        $entry->odometerEnd = $odometerEnd;
        if ($manager && $command->notBilled !== null) {
            $entry->notBilled = $command->notBilled;
        }
        $entry->odometerCheckBypassed = $bypassed;
        if ($existing === null && $external && $vehicle->logbookAnonymousRequiresApproval) {
            $entry->status = LogbookStatus::Pending;
        }
        $entry->updatedAt = $now;

        $changes = $before === null ? LogbookEntryChange::diff([], $entry->auditValues()) : LogbookEntryChange::diff($before, $entry->auditValues());
        if ($existing !== null && $changes === []) {
            return $entry->id;
        }

        $this->entries->save($entry);
        $this->entries->addChange(new LogbookEntryChange(
            Uuid::v4(),
            $entry->id,
            $actor?->id,
            $actor?->displayName() ?? ($driverName . ' (extern)'),
            $existing === null ? LogbookEntryChange::CREATED : LogbookEntryChange::UPDATED,
            $changes,
            $now,
        ));

        return $entry->id;
    }

    /**
     * @param array<string, string> $errors
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: bool}
     */
    private function driver(SaveLogbookEntry $command, ?User $actor, bool $manager, ?LogbookEntry $existing, array &$errors): array
    {
        if (!$manager) {
            // Drivers keep the driver of an existing trip and record new trips for themselves.
            if ($existing !== null) {
                return [$existing->driverUserId, $existing->driverName, $existing->driverPhone, $existing->isExternal];
            }

            return [$actor?->id, null, null, false];
        }

        $choice = trim((string) $command->driverUserId);
        if ($choice === '') {
            return [$actor?->id, null, null, false];
        }
        if ($choice === 'external') {
            $name = $this->text($command->driverName, 150);
            if ($name === null) {
                $errors['driverName'] = 'Bitte den Namen des Fahrers angeben.';
            }

            return [null, $name, $this->text($command->driverPhone, 50), true];
        }
        $driver = $this->users->findById($choice);
        if ($driver === null || (!$driver->isActive && $choice !== $existing?->driverUserId)) {
            $errors['driverUserId'] = 'Bitte einen aktiven Benutzer als Fahrer wählen.';

            return [null, null, null, false];
        }

        return [$driver->id, null, null, false];
    }

    /**
     * @param array<string, string> $errors
     * @return array{0: ?Vehicle, 1: ?string}
     */
    private function vehicle(SaveLogbookEntry $command, ?User $actor, ?LogbookEntry $existing, array &$errors): array
    {
        $choice = trim((string) $command->vehicleId);
        if ($choice === 'other') {
            $label = $this->text($command->otherVehicleLabel, 150);
            if ($label === null) {
                $errors['otherVehicleLabel'] = 'Bitte das Fahrzeug beschreiben (z. B. „Privat-PKW, M-XY 123“).';
            }

            return [null, $label];
        }
        if ($choice === '') {
            $errors['vehicleId'] = 'Bitte das Fahrzeug wählen.';

            return [null, null];
        }
        $vehicle = $this->vehicles->findById($choice);
        if ($vehicle === null || $vehicle->category !== VehicleCategory::Auto) {
            $errors['vehicleId'] = 'Bitte ein gültiges Fahrzeug wählen.';

            return [null, null];
        }
        if ($vehicle->id !== $existing?->vehicleId && !LogbookAccess::canUseVehicle($actor, $vehicle)) {
            $errors['vehicleId'] = 'Mit diesem Privatfahrzeug darfst du keine Fahrten erfassen.';

            return [null, null];
        }

        return [$vehicle, null];
    }

    /** @param array<string, string> $errors */
    private function trailer(?string $trailerId, ?User $actor, ?LogbookEntry $existing, array &$errors): ?Vehicle
    {
        $trailerId = trim((string) $trailerId);
        if ($trailerId === '') {
            return null;
        }
        $trailer = $this->vehicles->findById($trailerId);
        if ($trailer === null || $trailer->category !== VehicleCategory::Anhaenger) {
            $errors['trailerId'] = 'Bitte einen gültigen Anhänger wählen.';

            return null;
        }
        $allowed = $actor === null ? !$trailer->isPrivate : LogbookAccess::canUseVehicle($actor, $trailer);
        if ($trailer->id !== $existing?->trailerId && !$allowed) {
            $errors['trailerId'] = 'Mit diesem privaten Anhänger darfst du keine Fahrten erfassen.';

            return null;
        }

        return $trailer;
    }

    /** @param array<string, string> $errors */
    private function parseDate(string $value, array &$errors): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            $errors['tripDate'] = 'Bitte das Datum der Fahrt angeben.';

            return null;
        }
        if ($date > new DateTimeImmutable('tomorrow')) {
            $errors['tripDate'] = 'Fahrten können nicht im Voraus erfasst werden.';

            return null;
        }

        return $date;
    }

    /** @param array<string, string> $errors */
    private function parseTime(?string $value, string $field, array &$errors): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m) !== 1) {
            $errors[$field] = 'Bitte die Uhrzeit als HH:MM angeben.';

            return null;
        }

        return sprintf('%02d:%s', (int) $m[1], $m[2]);
    }

    /** @param array<string, string> $errors */
    private function parseKm(?string $value, string $field, array &$errors): ?int
    {
        $value = trim(str_replace(['.', ' '], '', (string) $value));
        if ($value === '') {
            return null;
        }
        // A decimal comma is rounded to whole kilometres.
        if (preg_match('/^\d+(,\d+)?$/', $value) !== 1) {
            $errors[$field] = 'Bitte eine ganze Zahl (km) angeben.';

            return null;
        }
        $km = (int) round((float) str_replace(',', '.', $value));
        if ($km > Vehicle::MAX_ODOMETER_KM) {
            $errors[$field] = 'Der Wert ist zu groß.';

            return null;
        }

        return $km;
    }

    private function text(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * New saved addresses are only created when the rest of the form is
     * valid, so a rejected form does not leave addresses behind.
     *
     * @param array<string, string> $errors
     */
    private function hasErrorsBeyondLocations(array $errors): bool
    {
        return array_diff_key($errors, ['from' => true, 'to' => true]) !== [];
    }
}
