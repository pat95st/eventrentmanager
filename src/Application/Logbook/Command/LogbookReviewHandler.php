<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ForbiddenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookAccess;
use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookEntry;
use App\Domain\Logbook\LogbookEntryChange;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookOdometerChain;
use App\Domain\Logbook\LogbookStatus;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Gap reports and approvals of the driver's logbook (migration 185).
 *
 * - ReportOdometerGap: only drivers who may use the vehicle, only for a
 *   reading above the last recorded one (beyond the tolerance). The gap is
 *   recorded as an "unclear" trip from the last to the current reading,
 *   pending until a logbook manager clarifies it.
 * - ReviewLogbookEntry: logbook managers approve or reject pending trips;
 *   rejected trips stay in the logbook (odometer chain) but are not billed.
 */
final class LogbookReviewHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LogbookEntryRepositoryInterface $entries,
        private readonly UserRepositoryInterface $users,
        private readonly VehicleRepositoryInterface $vehicles,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ReportOdometerGap || $command instanceof ReviewLogbookEntry);
        $actor = $this->users->findById($command->actorUserId);
        $now = new DateTimeImmutable();

        if ($command instanceof ReviewLogbookEntry) {
            if (!LogbookAccess::canManage($actor)) {
                throw new ForbiddenException('Nur Fahrtenbuch-Verwalter können Fahrten freigeben.');
            }
            $entry = $this->entries->findById($command->entryId);
            if ($entry === null || $entry->isDeleted()) {
                throw new NotFoundException('Die Fahrt wurde nicht gefunden.');
            }
            $before = $entry->auditValues();
            $entry->status = $command->approve ? LogbookStatus::Approved : LogbookStatus::Rejected;
            $entry->updatedAt = $now;
            $changes = LogbookEntryChange::diff($before, $entry->auditValues());
            if ($changes !== []) {
                $this->entries->save($entry);
                $this->entries->addChange(new LogbookEntryChange(Uuid::v4(), $entry->id, $actor?->id, $actor?->displayName(), LogbookEntryChange::UPDATED, $changes, $now));
            }

            return null;
        }

        $vehicle = $this->vehicles->findById($command->vehicleId);
        if ($vehicle === null || $actor === null || !$actor->isActive || !LogbookAccess::canUseVehicle($actor, $vehicle)) {
            throw new ForbiddenException('Du darfst für dieses Fahrzeug keine Fahrten erfassen.');
        }
        $chain = new LogbookOdometerChain($this->entries);
        $expected = $chain->expected($vehicle);
        if ($expected === null || !$chain->isGap($vehicle, $command->currentReading)) {
            throw new ValidationException(['odometer' => 'Eine Lücke kann nur gemeldet werden, wenn der aktuelle Kilometerstand über dem zuletzt erfassten liegt.']);
        }

        $entry = new LogbookEntry(
            id: Uuid::v4(),
            tripDate: $now->setTime(0, 0),
            departTime: null,
            arriveTime: null,
            driverUserId: null,
            driverName: 'Unbekannt',
            driverPhone: null,
            isExternal: false,
            vehicleId: $vehicle->id,
            otherVehicleLabel: null,
            trailerId: null,
            category: LogbookCategory::Unclear,
            rentalId: null,
            tourStopId: null,
            purpose: 'Lücke im Kilometerstand, gemeldet von ' . $actor->displayName(),
            from: null,
            to: null,
            roundTrip: false,
            distanceKm: $command->currentReading - $expected,
            odometerStart: $expected,
            odometerEnd: $command->currentReading,
            notBilled: false,
            status: LogbookStatus::Pending,
            odometerCheckBypassed: false,
            createdByUserId: $actor->id,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->entries->save($entry);
        $this->entries->addChange(new LogbookEntryChange(Uuid::v4(), $entry->id, $actor->id, $actor->displayName(), LogbookEntryChange::CREATED, LogbookEntryChange::diff([], $entry->auditValues()), $now));

        return $entry->id;
    }
}
