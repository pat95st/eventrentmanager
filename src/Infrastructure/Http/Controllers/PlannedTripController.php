<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Logbook\LogbookLocationResolver;
use App\Application\PlannedTrip\Command\DeletePlannedTrip;
use App\Application\PlannedTrip\Command\RecalculatePlannedTrips;
use App\Application\PlannedTrip\Command\RestoreDefaultPlannedTrips;
use App\Application\PlannedTrip\Command\SavePlannedTrip;
use App\Application\PlannedTrip\PlannedTripService;
use App\Application\Routing\Command\AddPlannedVehicle;
use App\Application\Routing\Command\RemovePlannedVehicle;
use App\Application\Routing\Command\SetTourTravelCosts;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookEntry;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookFilter;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Page "Fahrten & Fahrtkosten" of an event that is not a tour (migration
 * 186): planned trips (default outbound/return trip and custom trips),
 * planned vehicles, the travel cost switch and the trips actually recorded
 * in the driver's logbook. Tours are sent to their route page.
 */
final class PlannedTripController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly RentalRepositoryInterface $rentals,
        private readonly PlannedTripService $trips,
        private readonly LogbookLocationResolver $locations,
        private readonly PlannedVehicleRepositoryInterface $plannedVehicles,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly LogbookEntryRepositoryInterface $logbook,
    ) {
    }

    public function show(Request $request): void
    {
        $rental = $this->rental($request);
        if ($rental === null) {
            return;
        }
        $planned = [];
        foreach ($this->plannedVehicles->findByRental($rental->id) as $entry) {
            $vehicle = $this->vehicles->findById($entry->vehicleId);
            if ($vehicle !== null) {
                $planned[] = ['id' => $entry->id, 'vehicle' => $vehicle, 'towing' => $entry->towingVehicleId !== null ? $this->vehicles->findById($entry->towingVehicleId) : null];
            }
        }
        $actual = $this->logbook->search(new LogbookFilter(rentalId: $rental->id));

        echo View::page('rentals/planned-trips', [
            'rental' => $rental,
            'trips' => $this->trips->forRental($rental),
            'proposal' => $this->trips->proposal($rental),
            'planned' => $planned,
            'vehicles' => $this->vehicles->findAll(),
            'vehicleNames' => array_column(array_map(static fn ($v) => [$v->id, $v->displayName()], $this->vehicles->findAll()), 1, 0),
            'generalLocations' => $this->locations->generalOptions(),
            'rentalLocations' => $this->locations->rentalOptions($rental),
            'actual' => $actual,
            'actualBillableKm' => array_sum(array_map(static fn (LogbookEntry $e) => $e->isBillable() ? $e->distanceKm : 0, $actual)),
        ], activeNav: 'rentals', title: 'Fahrten – ' . $rental->eventName);
    }

    public function store(Request $request): void
    {
        $this->saveTrip($request, null);
    }

    public function update(Request $request): void
    {
        $this->saveTrip($request, $request->param('tripId'));
    }

    public function destroy(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new DeletePlannedTrip($id, $request->param('tripId'))), 'Die Fahrt wurde gelöscht.');
    }

    public function restoreDefaults(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new RestoreDefaultPlannedTrips($id)), 'Hin- und Rückfahrt wurden wiederhergestellt.');
    }

    public function recalculate(Request $request): void
    {
        $this->run($request, function (string $id): void {
            $missing = (int) $this->commands->dispatch(new RecalculatePlannedTrips($id));
            if ($missing > 0) {
                Flash::error($missing === 1 ? 'Für eine Fahrt konnte keine Strecke berechnet werden – bitte die Kilometer eintragen.' : 'Für ' . $missing . ' Fahrten konnte keine Strecke berechnet werden – bitte die Kilometer eintragen.');
            }
        }, 'Die Strecken wurden neu berechnet.');
    }

    public function travelCosts(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new SetTourTravelCosts($id, $request->input('enabled') === '1', $request->input('mode', 'summary') ?? 'summary')), 'Einstellungen zu den Fahrtkosten gespeichert.');
    }

    public function addVehicle(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new AddPlannedVehicle($id, $request->input('vehicle_id', '') ?? '', $request->input('towing_vehicle_id', '') ?? '')), 'Fahrzeug geplant.');
    }

    public function removeVehicle(Request $request): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new RemovePlannedVehicle($id, $request->param('plannedId'))), 'Fahrzeug entfernt.');
    }

    private function saveTrip(Request $request, ?string $tripId): void
    {
        $this->run($request, fn (string $id) => $this->commands->dispatch(new SavePlannedTrip(
            $id,
            $tripId,
            $request->input('trip_date'),
            $request->input('from'),
            $request->input('from_text'),
            $request->input('to'),
            $request->input('to_text'),
            $request->input('km_override'),
        )), $tripId === null ? 'Die Fahrt wurde hinzugefügt.' : 'Die Fahrt wurde geändert.');
    }

    /** @param callable(string): mixed $work */
    private function run(Request $request, callable $work, string $success): void
    {
        $rentalId = $request->param('id');
        try {
            $work($rentalId);
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', array_unique($e->errors())));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/veranstaltungen/' . $rentalId . '/fahrten');
    }

    private function rental(Request $request): ?Rental
    {
        $rental = $this->rentals->findById($request->param('id'));
        if ($rental === null) {
            Response::notFound('Die Veranstaltung wurde nicht gefunden.');

            return null;
        }
        if ($rental->isTour) {
            Response::redirect('/veranstaltungen/' . $rental->id . '/tour/route');

            return null;
        }

        return $rental;
    }
}
