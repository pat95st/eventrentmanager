<?php

declare(strict_types=1);

namespace App\Application\RentalSeries;

use App\Application\Command\CommandBus;
use App\Application\Common\VenueResolver;
use App\Application\Technician\Command\AssignTechnician;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\RentalSeries\CopyScope;
use App\Domain\RentalSeries\RentalSeries;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Takes data over from one rental into another (recurring events,
 * migration 176). Selected areas replace the target's data; rentals whose
 * material has already been issued must not be used as target (see
 * isStarted()).
 */
final class RentalCopier
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly RentalChildDataCopierInterface $childData,
        private readonly RentalTechnicianRepositoryInterface $technicians,
        private readonly VenueRepositoryInterface $venues,
        private readonly ?CommandBus $commands = null,
        private readonly ?\App\Application\Tour\TourSync $tourSync = null,
    ) {
    }

    /** Material of this rental has already been issued: it is no longer changed by series actions. */
    public function isStarted(Rental $rental): bool
    {
        return $this->issuedItems->findByRental($rental->id) !== [];
    }

    /**
     * Creates a new occurrence of $series from $source on $startDate. The
     * duration and (with Logistics) the pickup/return offsets are taken over
     * relative to the new date. $locationOverride (free text, matched
     * against saved venues) replaces the source's location.
     *
     * @param CopyScope[] $scopes
     */
    public function createOccurrence(
        Rental $source,
        RentalSeries $series,
        string $baseName,
        DateTimeImmutable $startDate,
        ?DateTimeImmutable $endDate,
        ?string $locationOverride,
        int $index,
        array $scopes,
        bool $customized = false,
    ): Rental {
        $startDate = $startDate->setTime(0, 0);
        $endDate = ($endDate ?? $startDate->modify('+' . $this->durationDays($source) . ' days'))->setTime(0, 0);
        if ($endDate < $startDate) {
            $endDate = $startDate;
        }
        $now = new DateTimeImmutable();
        $rental = new Rental(
            id: Uuid::v4(),
            customerId: $source->customerId,
            startDate: $startDate,
            endDate: $endDate,
            eventName: $series->nameFor($baseName, $startDate, $index),
            eventLocation: null,
            venueId: null,
            deploymentDays: min($source->deploymentDays, $startDate->diff($endDate)->days + 1),
            createdAt: $now,
            updatedAt: $now,
            blocksOtherRentals: $source->blocksOtherRentals,
            noStrobe: $source->noStrobe,
            isTour: $source->isTour && in_array(CopyScope::TourStops, $scopes, true),
            seriesId: $series->id,
            seriesIndex: $index,
            seriesCustomized: $customized,
            // Migration 182: occurrences bill travel costs like the original tour.
            travelCostsEnabled: $source->travelCostsEnabled,
            travelCostsMode: $source->travelCostsMode,
        );
        if ($locationOverride !== null) {
            // Set before copying so the power plan sees the final venue.
            [$rental->eventLocation, $rental->venueId] = VenueResolver::resolve($this->venues, $locationOverride);
            if (in_array(CopyScope::Location, $scopes, true)) {
                $rental->transportNote = $source->transportNote;
            }
            $scopes = array_values(array_filter($scopes, static fn (CopyScope $s): bool => $s !== CopyScope::Location));
        }
        $this->rentals->save($rental);
        $this->copy($source, $rental, $scopes);

        return $rental;
    }

    /**
     * Replaces the selected areas of $target with the data of $source.
     *
     * @param CopyScope[] $scopes
     */
    public function copy(Rental $source, Rental $target, array $scopes): void
    {
        if ($source->id === $target->id) {
            return;
        }
        if (in_array(CopyScope::Location, $scopes, true)) {
            $target->venueId = $source->venueId;
            $target->eventLocation = $source->eventLocation;
            $target->transportNote = $source->transportNote;
        }
        if (in_array(CopyScope::Logistics, $scopes, true)) {
            $target->pickupWarehouseId = $source->pickupWarehouseId;
            $target->returnWarehouseId = $source->returnWarehouseId;
            $target->pickupAt = self::moveRelative($source->pickupAt, $source->startDate, $target->startDate);
            $target->returnAt = self::moveRelative($source->returnAt, $source->endDate, $target->endDate);
            if (!in_array(CopyScope::Location, $scopes, true)) {
                $target->transportNote = $source->transportNote;
            }
        }
        $target->updatedAt = new DateTimeImmutable();
        $this->rentals->save($target);

        if (in_array(CopyScope::Material, $scopes, true)) {
            $this->childData->replaceMaterial($source->id, $target->id);
        }
        if (in_array(CopyScope::MinuteProgram, $scopes, true)) {
            $this->childData->replaceMinutePrograms($source->id, $target->id, self::dayOffset($source->startDate, $target->startDate));
        }
        if (in_array(CopyScope::PowerPlan, $scopes, true)) {
            $this->childData->replacePowerPlan($source->id, $target->id, $source->venueId !== null && $source->venueId === $target->venueId);
        }
        if (in_array(CopyScope::Technicians, $scopes, true)) {
            $this->childData->replaceTrades($source->id, $target->id);
            $this->replaceTechnicians($source, $target);
        }
        if (in_array(CopyScope::TourStops, $scopes, true) && $source->isTour) {
            if (!$target->isTour) {
                $target->isTour = true;
                $this->rentals->save($target);
            }
            $this->childData->replaceTourStops($source->id, $target->id, self::dayOffset($source->startDate, $target->startDate));
        }
        if ($target->isTour) {
            $this->tourSync?->sync($target);
        }
    }

    /**
     * Moves an occurrence to a new start date (rule change): duration,
     * pickup/return times and minute programs move along.
     */
    public function moveOccurrence(Rental $rental, DateTimeImmutable $newStart, ?RentalSeries $series, string $baseName): void
    {
        $newStart = $newStart->setTime(0, 0);
        $offset = self::dayOffset($rental->startDate, $newStart);
        if ($offset === 0) {
            return;
        }
        $rental->startDate = $newStart;
        $rental->endDate = $rental->endDate->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
        $rental->pickupAt = $rental->pickupAt?->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
        $rental->returnAt = $rental->returnAt?->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
        if ($series !== null && $series->nameTemplate !== null && $series->nameTemplate !== '') {
            $rental->eventName = $series->nameFor($baseName, $newStart, $rental->seriesIndex ?? 1);
        }
        $rental->updatedAt = new DateTimeImmutable();
        $this->rentals->save($rental);
        $this->childData->shiftMinutePrograms($rental->id, $offset);
        if ($rental->isTour) {
            $this->childData->shiftTourStops($rental->id, $offset);
        }
    }

    /** Technicians of the target that are not in the source are removed, the others are (re)requested. */
    private function replaceTechnicians(Rental $source, Rental $target): void
    {
        $sourceAssignments = array_values(array_filter(
            $this->technicians->findByRental($source->id),
            static fn ($a) => $a->status !== RentalTechnicianStatus::Declined,
        ));
        $sourceUserIds = array_map(static fn ($a) => $a->userId, $sourceAssignments);
        foreach ($this->technicians->findByRental($target->id) as $existing) {
            if (!in_array($existing->userId, $sourceUserIds, true)) {
                $this->technicians->delete($existing->id);
            }
        }
        if ($this->commands === null) {
            return;
        }
        foreach ($sourceAssignments as $assignment) {
            try {
                $this->commands->dispatch(new AssignTechnician($target->id, $assignment->userId, $assignment->tradeIds, $assignment->note));
            } catch (\App\Domain\Exception\ValidationException) {
                // Technician no longer active: skip, the rest is copied anyway.
            }
        }
    }

    private function durationDays(Rental $rental): int
    {
        return $rental->startDate->setTime(0, 0)->diff($rental->endDate->setTime(0, 0))->days;
    }

    public static function dayOffset(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->setTime(0, 0)->diff($to->setTime(0, 0))->format('%r%a');
    }

    /** Same distance (in days, same time of day) to $newReference as $moment had to $oldReference. */
    private static function moveRelative(?DateTimeImmutable $moment, DateTimeImmutable $oldReference, DateTimeImmutable $newReference): ?DateTimeImmutable
    {
        if ($moment === null) {
            return null;
        }
        $days = self::dayOffset($oldReference, $moment);
        $date = $newReference->setTime(0, 0)->modify(($days >= 0 ? '+' : '') . $days . ' days');

        return $date->setTime((int) $moment->format('H'), (int) $moment->format('i'));
    }
}
