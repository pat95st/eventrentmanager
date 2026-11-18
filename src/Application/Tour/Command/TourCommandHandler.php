<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandBus;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Common\VenueResolver;
use App\Application\MinuteProgram\Command\CreateMinuteProgram;
use App\Application\Technician\Command\AssignTechnician;
use App\Application\Tour\TourCrewNotifierInterface;
use App\Application\Tour\TourSync;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\Tour\TourAllocation;
use App\Domain\Tour\TourCrewAction;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Tour\TourSavedAddress;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\TourStopItem;
use App\Domain\Tour\TourStopKind;
use App\Domain\Tour\TourStopTechnician;
use App\Domain\Tour\WaypointAddressType;
use App\Domain\Tour\WaypointPurpose;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use App\Infrastructure\Geocoding\GeocoderInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Tour planning (migration 177): stops, waypoints, material and crew
 * deviations per stop. Stops whose teardown already lies in the past are
 * locked for bulk changes.
 */
final class TourCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly TourRepositoryInterface $tours,
        private readonly RentalRepositoryInterface $rentals,
        private readonly TourSync $sync,
        private readonly VenueRepositoryInterface $venues,
        private readonly ?ArticleRepositoryInterface $articles = null,
        private readonly ?InventoryNumberRepositoryInterface $inventoryNumbers = null,
        private readonly ?WarehouseRepositoryInterface $warehouses = null,
        private readonly ?CompanySettingsRepositoryInterface $company = null,
        private readonly ?GeocoderInterface $geocoder = null,
        private readonly ?RentalTechnicianRepositoryInterface $technicians = null,
        private readonly ?UserRepositoryInterface $users = null,
        private readonly ?CommandBus $commands = null,
        private readonly ?TourCrewNotifierInterface $crewNotifier = null,
        private readonly ?DateTimeImmutable $now = null,
        private readonly ?\App\Application\Routing\CompanyLocator $companyLocator = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof MakeTour => $this->makeTour($command),
            $command instanceof RevertTour => $this->revertTour($command),
            $command instanceof SaveTourStop => $this->saveStop($command),
            $command instanceof SaveTourWaypoint => $this->saveWaypoint($command),
            $command instanceof DeleteTourStop => $this->deleteStop($command),
            $command instanceof ApplyTourMaterialChange => $this->applyMaterial($command),
            $command instanceof DeleteTourStopItem => $this->deleteItem($command),
            $command instanceof ApplyTourCrewChange => $this->applyCrew($command),
            $command instanceof DeleteTourCrewDeviation => $this->deleteCrewDeviation($command),
            $command instanceof CreateTourStopMinuteProgram => $this->createMinuteProgram($command),
            default => throw new \InvalidArgumentException('Unsupported command ' . $command::class),
        };
    }

    private function makeTour(MakeTour $command): null
    {
        $rental = $this->rental($command->rentalId);
        if ($rental->isTour) {
            return null;
        }
        $rental->isTour = true;
        $rental->updatedAt = new DateTimeImmutable();
        $this->rentals->save($rental);
        $this->tours->saveStop(new TourStop(
            id: Uuid::v4(),
            rentalId: $rental->id,
            kind: TourStopKind::Stop,
            venueId: $rental->venueId,
            locationText: $rental->eventLocation,
            eventStartAt: $rental->startDate->setTime(0, 0),
            eventEndAt: $rental->endDate->setTime(23, 59),
        ));

        return null;
    }

    private function revertTour(RevertTour $command): null
    {
        $rental = $this->rental($command->rentalId);
        $stops = $this->sync->plan($rental->id)->stops();
        if (count($stops) > 1) {
            throw new ValidationException(['tour' => 'Die Tour hat mehrere Stops. Bitte zuerst alle Stops bis auf einen löschen.']);
        }
        foreach ($this->tours->findStopsByRental($rental->id) as $stop) {
            $this->tours->deleteStop($stop->id);
        }
        $rental->isTour = false;
        $rental->updatedAt = new DateTimeImmutable();
        $this->rentals->save($rental);
        $this->tours->syncExtraQuantities($rental->id, []);

        return null;
    }

    private function saveStop(SaveTourStop $command): string
    {
        $rental = $this->tour($command->rentalId);
        $existing = $command->stopId !== null ? $this->stopOf($rental, $command->stopId) : null;
        $errors = [];
        $setup = $this->moment($command->setupAt, 'setupAt', $errors);
        $start = $this->moment($command->eventStartAt, 'eventStartAt', $errors);
        $end = $this->moment($command->eventEndAt, 'eventEndAt', $errors);
        $teardown = $this->moment($command->teardownEndAt, 'teardownEndAt', $errors);
        if ($start === null && !isset($errors['eventStartAt'])) {
            $errors['eventStartAt'] = 'Bitte den Beginn der Veranstaltung angeben.';
        }
        if ($start !== null && $end !== null && $end < $start) {
            $errors['eventEndAt'] = 'Das Ende liegt vor dem Beginn.';
        }
        if ($start !== null && $setup !== null && $setup > $start) {
            $errors['setupAt'] = 'Der Aufbau muss vor dem Beginn liegen.';
        }
        if ($teardown !== null && ($end ?? $start) !== null && $teardown < ($end ?? $start)) {
            $errors['teardownEndAt'] = 'Das Abbau-Ende muss nach dem Ende der Veranstaltung liegen.';
        }
        if (trim($command->location) === '') {
            $errors['location'] = 'Bitte einen Ort angeben.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        [$location, $venueId] = VenueResolver::resolve($this->venues, $command->location);

        $stop = $existing ?? new TourStop(Uuid::v4(), $rental->id, TourStopKind::Stop);
        $stop->locationText = $location;
        $stop->venueId = $venueId;
        $stop->setupAt = $setup;
        $stop->eventStartAt = $start;
        $stop->eventEndAt = $end;
        $stop->teardownEndAt = $teardown;
        $stop->contactName = self::nullable($command->contactName, 150);
        $stop->contactPhone = self::nullable($command->contactPhone, 50);
        $stop->note = self::nullable($command->note, 5000);
        $this->tours->saveStop($stop);
        $this->sync->sync($rental);

        return $stop->id;
    }

    private function saveWaypoint(SaveTourWaypoint $command): string
    {
        $rental = $this->tour($command->rentalId);
        $kind = in_array($command->kind, ['start', 'end'], true) ? TourStopKind::from($command->kind) : TourStopKind::Waypoint;
        $existing = $command->waypointId !== null ? $this->stopOf($rental, $command->waypointId) : null;
        if ($kind !== TourStopKind::Waypoint) {
            // Only one start and one destination per tour.
            foreach ($this->tours->findStopsByRental($rental->id) as $candidate) {
                if ($candidate->kind === $kind) {
                    $existing = $candidate;
                }
            }
        }
        $errors = [];
        $purpose = $kind === TourStopKind::Waypoint ? (WaypointPurpose::tryFrom($command->purpose) ?? WaypointPurpose::Other) : null;
        $type = WaypointAddressType::tryFrom($command->addressType);
        $arrive = $this->moment($command->arriveAt, 'arriveAt', $errors);
        $depart = $this->moment($command->departAt, 'departAt', $errors);
        if ($arrive !== null && $depart !== null && $depart < $arrive) {
            $errors['departAt'] = 'Die Abfahrt liegt vor der Ankunft.';
        }
        $afterStopId = $kind === TourStopKind::Waypoint && trim($command->afterStopId) !== '' ? $command->afterStopId : null;
        if ($afterStopId !== null && !$this->stopOf($rental, $afterStopId)->isStop()) {
            $errors['afterStopId'] = 'Bitte einen Stop auswählen.';
        }

        $waypoint = $existing ?? new TourStop(Uuid::v4(), $rental->id, $kind);
        $waypoint->purpose = $purpose;
        $waypoint->addressType = $type;
        $waypoint->afterStopId = $afterStopId;
        $waypoint->arriveAt = $arrive;
        $waypoint->departAt = $depart;
        $waypoint->note = self::nullable($command->note, 5000);
        $waypoint->warehouseId = null;
        $waypoint->savedAddressId = null;

        switch ($type) {
            case WaypointAddressType::Warehouse:
                $warehouse = $command->warehouseId !== '' ? $this->warehouses?->findById($command->warehouseId) : null;
                if ($warehouse === null) {
                    $errors['warehouseId'] = 'Bitte ein Lager auswählen.';
                    break;
                }
                $waypoint->warehouseId = $warehouse->id;
                $this->setAddress($waypoint, $warehouse->name, $warehouse->street, $warehouse->postalCode, $warehouse->city, $warehouse->country, $warehouse->latitude, $warehouse->longitude);
                break;
            case WaypointAddressType::Company:
                $located = $this->companyLocator?->locate();
                if ($located !== null) {
                    $this->setAddress($waypoint, $located['label'], $located['street'], $located['postalCode'], $located['city'], $located['country'], $located['lat'], $located['lng']);
                    break;
                }
                $company = $this->company?->get();
                if ($company === null || trim((string) $company->city) === '') {
                    $errors['addressType'] = 'In den Firmendaten ist keine Adresse hinterlegt.';
                    break;
                }
                $this->setAddress($waypoint, $company->name ?? 'Firma', (string) $company->street, (string) $company->postalCode, (string) $company->city, $company->country ?? null, null, null);
                break;
            case WaypointAddressType::Saved:
                $saved = $command->savedAddressId !== '' ? $this->tours->findSavedAddress($command->savedAddressId) : null;
                if ($saved === null) {
                    $errors['savedAddressId'] = 'Bitte eine gespeicherte Adresse auswählen.';
                    break;
                }
                $waypoint->savedAddressId = $saved->id;
                $this->setAddress($waypoint, $saved->label, $saved->street, $saved->postalCode, $saved->city, $saved->country, $saved->latitude, $saved->longitude);
                break;
            case WaypointAddressType::Custom:
                if (trim($command->street) === '' || trim($command->postalCode) === '' || trim($command->city) === '') {
                    $errors['street'] = 'Bitte Straße, PLZ und Ort angeben.';
                    break;
                }
                $country = self::nullable($command->country, 100);
                $coordinates = $this->geocode($command->street, $command->postalCode, $command->city, $country);
                $label = self::nullable($command->label, 255);
                $this->setAddress($waypoint, $label, trim($command->street), trim($command->postalCode), trim($command->city), $country, $coordinates[0] ?? null, $coordinates[1] ?? null);
                if ($command->rememberAddress) {
                    $saved = new TourSavedAddress(Uuid::v4(), $label ?? trim($command->city), trim($command->street), trim($command->postalCode), trim($command->city), $country, $coordinates[0] ?? null, $coordinates[1] ?? null);
                    $this->tours->saveSavedAddress($saved);
                    $waypoint->addressType = WaypointAddressType::Saved;
                    $waypoint->savedAddressId = $saved->id;
                }
                break;
            default:
                $errors['addressType'] = 'Bitte eine Adressart auswählen.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->tours->saveStop($waypoint);

        return $waypoint->id;
    }

    private function deleteStop(DeleteTourStop $command): null
    {
        $stop = $this->tours->findStop($command->stopId) ?? throw new NotFoundException('Der Stop wurde nicht gefunden.');
        $rental = $this->tour($stop->rentalId);
        if ($stop->isStop() && count($this->sync->plan($rental->id)->stops()) <= 1) {
            throw new ValidationException(['stop' => 'Eine Tour braucht mindestens einen Stop.']);
        }
        $this->tours->deleteStop($stop->id);
        $this->sync->sync($rental);

        return null;
    }

    /** @return int number of stops changed */
    private function applyMaterial(ApplyTourMaterialChange $command): int
    {
        $rental = $this->tour($command->rentalId);
        $stops = $this->selectedStops($rental, $command->stopIds);
        $article = $this->articles?->findById($command->articleId);
        if ($this->articles !== null && $article === null) {
            throw new ValidationException(['articleId' => 'Bitte einen Artikel auswählen.']);
        }
        $quantity = filter_var(trim($command->quantity), FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0) {
            throw new ValidationException(['quantity' => 'Bitte eine Menge von 0 oder mehr angeben.']);
        }
        if ($this->inventoryNumbers !== null) {
            $total = $this->inventoryNumbers->countActiveByArticles([$command->articleId])[$command->articleId] ?? 0;
            if ($quantity > $total) {
                throw new ValidationException(['quantity' => "Für diesen Artikel gibt es nur {$total} Inventarnummer(n)."]);
            }
        }
        $allocation = TourAllocation::tryFrom($command->allocation) ?? TourAllocation::StopsOnly;
        $waypointIds = array_map(static fn (TourStop $s) => $s->id, array_filter($this->tours->findStopsByRental($rental->id), static fn (TourStop $s) => $s->isWaypoint()));
        $issueWaypoint = in_array($command->issueWaypointId, $waypointIds, true) ? $command->issueWaypointId : null;
        $returnWaypoint = in_array($command->returnWaypointId, $waypointIds, true) ? $command->returnWaypointId : null;

        $base = $this->sync->baseList($rental->id)[$command->articleId] ?? 0;
        $delta = $quantity - $base;
        foreach ($stops as $stop) {
            $existing = $this->tours->findItem($stop->id, $command->articleId);
            if ($delta === 0) {
                if ($existing !== null) {
                    $this->tours->deleteItem($existing->id);
                }
                continue;
            }
            $item = $existing ?? new TourStopItem(Uuid::v4(), $stop->id, $command->articleId, $delta);
            $item->delta = $delta;
            $item->allocation = $allocation;
            $item->issueWaypointId = $issueWaypoint;
            $item->returnWaypointId = $returnWaypoint;
            $this->tours->saveItem($item);
        }
        $this->sync->sync($rental);

        return count($stops);
    }

    private function deleteItem(DeleteTourStopItem $command): null
    {
        $rental = $this->tour($command->rentalId);
        foreach ($this->tours->findItemsByRental($rental->id) as $item) {
            if ($item->id === $command->itemId) {
                $this->tours->deleteItem($item->id);
            }
        }
        $this->sync->sync($rental);

        return null;
    }

    /** @return array{changed: int, requested: bool} */
    private function applyCrew(ApplyTourCrewChange $command): array
    {
        $rental = $this->tour($command->rentalId);
        $stops = $this->selectedStops($rental, $command->stopIds);
        $action = TourCrewAction::tryFrom($command->action) ?? throw new ValidationException(['action' => 'Bitte eine Aktion auswählen.']);
        $userId = trim($command->userId);
        $replaces = trim($command->replacesUserId) !== '' ? trim($command->replacesUserId) : null;
        if ($userId === '' || ($this->users !== null && $this->users->findById($userId) === null)) {
            throw new ValidationException(['userId' => 'Bitte einen Techniker auswählen.']);
        }
        if ($action === TourCrewAction::Replace && ($replaces === null || $replaces === $userId)) {
            throw new ValidationException(['replacesUserId' => 'Bitte auswählen, wer vertreten wird.']);
        }
        if ($action !== TourCrewAction::Replace) {
            $replaces = null;
        }

        $existing = $this->tours->findTechniciansByRental($rental->id);
        // Someone who already belongs to the whole tour cannot be added again; replacing
        // another person then only means that the other person is not there.
        $assignment = $this->technicians?->findByRentalAndUser($rental->id, $userId);
        $onlyByDeviation = array_filter($existing, static fn (TourStopTechnician $d) => $d->userId === $userId && $d->action !== TourCrewAction::Remove) !== [];
        if ($assignment !== null && $assignment->status !== RentalTechnicianStatus::Declined && !$onlyByDeviation) {
            if ($action === TourCrewAction::Add) {
                throw new ValidationException(['userId' => 'Dieser Techniker ist bereits für die ganze Tour eingeplant.']);
            }
            if ($action === TourCrewAction::Replace) {
                $action = TourCrewAction::Remove;
                $userId = (string) $replaces;
                $replaces = null;
            }
        }
        $stopIds = array_map(static fn (TourStop $s) => $s->id, $stops);
        foreach ($existing as $deviation) {
            // One deviation per person and stop: the newest wins.
            $concerns = $deviation->userId === $userId || ($replaces !== null && $deviation->userId === $replaces);
            if (in_array($deviation->stopId, $stopIds, true) && $concerns) {
                $this->tours->deleteTechnician($deviation->id);
            }
        }
        foreach ($stops as $stop) {
            $this->tours->saveTechnician(new TourStopTechnician(Uuid::v4(), $stop->id, $action, $userId, $replaces));
        }

        $plan = $this->sync->plan($rental->id);
        $labels = array_map(fn (TourStop $s) => 'Stop ' . $plan->numberOf($s->id) . ': ' . $s->title() . ($s->eventStartAt !== null ? ' (' . $s->eventStartAt->format('d.m.Y') . ')' : ''), $stops);

        // A replacement or additional technician gets an ordinary request for the tour (with notification).
        $requested = false;
        if ($action !== TourCrewAction::Remove && $this->commands !== null && $this->technicians !== null) {
            $assignment = $this->technicians->findByRentalAndUser($rental->id, $userId);
            if ($assignment === null || $assignment->status === RentalTechnicianStatus::Declined) {
                $replacedName = $replaces !== null ? ($this->users?->findById($replaces)?->displayName() ?? '') : '';
                $note = ($action === TourCrewAction::Replace ? 'Vertretung' . ($replacedName !== '' ? ' für ' . $replacedName : '') : 'Zusätzlich')
                    . ' an: ' . implode('; ', $labels);
                $this->commands->dispatch(new AssignTechnician($rental->id, $userId, $assignment?->tradeIds ?? [], $note));
                $requested = true;
            }
        }
        $removedUser = $action === TourCrewAction::Remove ? $userId : $replaces;
        if ($removedUser !== null) {
            $this->crewNotifier?->notifyRemoved($rental, $removedUser, $labels, $action === TourCrewAction::Replace ? $this->users?->findById($userId)?->displayName() : null);
        }

        return ['changed' => count($stops), 'requested' => $requested];
    }

    private function deleteCrewDeviation(DeleteTourCrewDeviation $command): null
    {
        $rental = $this->tour($command->rentalId);
        foreach ($this->tours->findTechniciansByRental($rental->id) as $deviation) {
            if ($deviation->id === $command->deviationId) {
                $this->tours->deleteTechnician($deviation->id);
            }
        }

        return null;
    }

    private function createMinuteProgram(CreateTourStopMinuteProgram $command): string
    {
        $stop = $this->tours->findStop($command->stopId) ?? throw new NotFoundException('Der Stop wurde nicht gefunden.');
        $rental = $this->tour($stop->rentalId);
        if (!$stop->isStop() || $this->commands === null) {
            throw new ValidationException(['stop' => 'Ein Minutenprogramm kann nur für einen Stop angelegt werden.']);
        }
        $number = $this->sync->plan($rental->id)->numberOf($stop->id);
        $programId = (string) $this->commands->dispatch(new CreateMinuteProgram(
            $rental->id,
            mb_substr('Stop ' . $number . ' – ' . $stop->title(), 0, 255),
            null,
            ($stop->eventStartAt ?? $rental->startDate)->format('Y-m-d\TH:i'),
            false,
            0,
            0,
        ));
        $this->tours->assignMinuteProgram($programId, $stop->id);

        return $programId;
    }

    // --- helpers -------------------------------------------------------

    private function rental(string $id): Rental
    {
        return $this->rentals->findById($id) ?? throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
    }

    private function tour(string $id): Rental
    {
        $rental = $this->rental($id);
        if (!$rental->isTour) {
            throw new ValidationException(['tour' => 'Diese Veranstaltung ist keine Tour.']);
        }

        return $rental;
    }

    private function stopOf(Rental $rental, string $stopId): TourStop
    {
        $stop = $this->tours->findStop($stopId);
        if ($stop === null || $stop->rentalId !== $rental->id) {
            throw new NotFoundException('Der Stop wurde nicht gefunden.');
        }

        return $stop;
    }

    /**
     * @param string[] $stopIds
     * @return TourStop[]
     */
    private function selectedStops(Rental $rental, array $stopIds): array
    {
        $now = $this->now ?? new DateTimeImmutable();
        $stops = [];
        foreach ($this->sync->plan($rental->id)->stops() as $stop) {
            if (!in_array($stop->id, $stopIds, true)) {
                continue;
            }
            $until = $stop->occupiedUntil();
            if ($until !== null && $until < $now) {
                throw new ValidationException(['stops' => 'Stop „' . $stop->title() . '“ liegt in der Vergangenheit und kann nicht mehr geändert werden.']);
            }
            $stops[] = $stop;
        }
        if ($stops === []) {
            throw new ValidationException(['stops' => 'Bitte mindestens einen Stop auswählen.']);
        }

        return $stops;
    }

    private function setAddress(TourStop $waypoint, ?string $label, string $street, string $postalCode, string $city, ?string $country, ?float $lat, ?float $lng): void
    {
        $waypoint->label = $label;
        $waypoint->street = $street;
        $waypoint->postalCode = $postalCode;
        $waypoint->city = $city;
        $waypoint->country = $country;
        $waypoint->latitude = $lat;
        $waypoint->longitude = $lng;
    }

    /** @return array{0: float, 1: float}|null */
    private function geocode(string $street, string $postalCode, string $city, ?string $country): ?array
    {
        try {
            return $this->geocoder?->geocode(trim($street), trim($postalCode), trim($city), $country);
        } catch (\Throwable) {
            return null; // coordinates are optional; the route (stage 3) shows a hint
        }
    }

    /** @param array<string, string> $errors */
    private function moment(string $value, string $field, array &$errors): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($parsed !== false) {
                return $parsed;
            }
        }
        $errors[$field] = 'Bitte ein gültiges Datum mit Uhrzeit angeben.';

        return null;
    }

    private static function nullable(string $value, int $max): ?string
    {
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
