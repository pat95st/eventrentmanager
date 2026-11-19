<?php

declare(strict_types=1);

namespace App\Application\Routing\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Routing\PlannedVehicle;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Routing\RouteProfile;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Tour\TourStopKind;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/** Route planning (migration 178): settings, route profile and planned vehicles of an event. */
final class RoutingCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RoutingSettingsRepositoryInterface $settings,
        private readonly RentalRepositoryInterface $rentals,
        private readonly PlannedVehicleRepositoryInterface $plannedVehicles,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly ?TourRepositoryInterface $tours = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SaveRoutingSettings => $this->saveSettings($command),
            $command instanceof SetRouteProfile => $this->setProfile($command),
            $command instanceof AddPlannedVehicle => $this->addVehicle($command),
            $command instanceof RemovePlannedVehicle => $this->removeVehicle($command),
            $command instanceof SetLegBilling => $this->setLegBilling($command),
            default => throw new \InvalidArgumentException('Unsupported command ' . $command::class),
        };
    }

    private function saveSettings(SaveRoutingSettings $command): null
    {
        $buffer = filter_var(trim($command->bufferMinutes), FILTER_VALIDATE_INT);
        if ($buffer === false || $buffer < 0 || $buffer > 600) {
            throw new ValidationException(['bufferMinutes' => 'Bitte einen Puffer zwischen 0 und 600 Minuten angeben.']);
        }
        $settings = $this->settings->get();
        $key = trim($command->apiKey);
        if ($command->removeKey) {
            $settings->apiKey = null;
        } elseif ($key !== '') {
            if (mb_strlen($key) > 255) {
                throw new ValidationException(['apiKey' => 'Der API-Schlüssel ist zu lang.']);
            }
            $settings->apiKey = $key;
        }
        $settings->bufferMinutes = $buffer;
        $this->settings->save($settings);

        return null;
    }

    private function setProfile(SetRouteProfile $command): null
    {
        $rental = $this->rentals->findById($command->rentalId) ?? throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
        $profile = RouteProfile::tryFrom($command->profile) ?? throw new ValidationException(['profile' => 'Bitte ein Fahrprofil auswählen.']);
        $rental->routeProfile = $profile->value;
        $rental->updatedAt = new DateTimeImmutable();
        $this->rentals->save($rental);

        return null;
    }

    private function addVehicle(AddPlannedVehicle $command): string
    {
        $this->rentals->findById($command->rentalId) ?? throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
        $vehicle = $this->vehicles->findById($command->vehicleId) ?? throw new ValidationException(['vehicleId' => 'Bitte ein Fahrzeug auswählen.']);
        $towing = null;
        if (trim($command->towingVehicleId) !== '') {
            $towing = $this->vehicles->findById($command->towingVehicleId);
            if ($towing === null || !$towing->isAuto() || !$vehicle->isAnhaenger()) {
                throw new ValidationException(['towingVehicleId' => 'Ein Zugfahrzeug kann nur für einen Anhänger gewählt werden und muss ein Auto sein.']);
            }
        }
        foreach ($this->plannedVehicles->findByRental($command->rentalId) as $existing) {
            if ($existing->vehicleId === $vehicle->id) {
                throw new ValidationException(['vehicleId' => 'Dieses Fahrzeug ist bereits geplant.']);
            }
        }
        $planned = new PlannedVehicle(Uuid::v4(), $command->rentalId, $vehicle->id, $towing?->id);
        $this->plannedVehicles->save($planned);
        // A towing car is planned as well.
        if ($towing !== null && array_filter($this->plannedVehicles->findByRental($command->rentalId), static fn (PlannedVehicle $p) => $p->vehicleId === $towing->id) === []) {
            $this->plannedVehicles->save(new PlannedVehicle(Uuid::v4(), $command->rentalId, $towing->id));
        }

        return $planned->id;
    }

    private function removeVehicle(RemovePlannedVehicle $command): null
    {
        foreach ($this->plannedVehicles->findByRental($command->rentalId) as $planned) {
            if ($planned->id === $command->plannedVehicleId) {
                $this->plannedVehicles->delete($planned->id);
            }
        }

        return null;
    }

    /** Returns the rental id of the stop. */
    private function setLegBilling(SetLegBilling $command): string
    {
        $stop = $this->tours?->findStop($command->stopId) ?? throw new NotFoundException('Der Tourstop wurde nicht gefunden.');
        if ($stop->kind === TourStopKind::Start) {
            throw new ValidationException(['stop' => 'Zum Start der Tour gibt es keine Anfahrt.']);
        }
        $stop->arrivalNotBilled = !$command->billed;
        $this->tours->saveStop($stop);

        return $stop->rentalId;
    }
}
