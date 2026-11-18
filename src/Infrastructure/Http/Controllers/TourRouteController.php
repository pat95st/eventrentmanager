<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Routing\Command\AddPlannedVehicle;
use App\Application\Routing\Command\RemovePlannedVehicle;
use App\Application\Routing\Command\SaveRoutingSettings;
use App\Application\Routing\Command\SetRouteProfile;
use App\Application\Routing\Query\GetTourRoute;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Routing\PlannedVehicleRepositoryInterface;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Route of a tour, planned vehicles, route profile and the settings page (migration 178). */
final class TourRouteController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly PlannedVehicleRepositoryInterface $plannedVehicles,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly TourRepositoryInterface $tours,
        private readonly RoutingSettingsRepositoryInterface $settings,
    ) {
    }

    public function show(Request $request): void
    {
        $rentalId = $request->param('id');
        try {
            $route = $this->queries->ask(new GetTourRoute($rentalId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        if (!$route->rental->isTour) {
            Response::redirect('/veranstaltungen/' . $rentalId);

            return;
        }
        $planned = [];
        foreach ($this->plannedVehicles->findByRental($rentalId) as $entry) {
            $vehicle = $this->vehicles->findById($entry->vehicleId);
            if ($vehicle !== null) {
                $planned[] = ['id' => $entry->id, 'vehicle' => $vehicle, 'towing' => $entry->towingVehicleId !== null ? $this->vehicles->findById($entry->towingVehicleId) : null];
            }
        }
        $endpoints = ['start' => null, 'end' => null];
        foreach ($this->tours->findStopsByRental($rentalId) as $stop) {
            if (in_array($stop->kind->value, ['start', 'end'], true)) {
                $endpoints[$stop->kind->value] = $stop;
            }
        }
        echo View::page('rentals/tour/route', [
            'route' => $route,
            'planned' => $planned,
            'vehicles' => $this->vehicles->findAll(),
            'endpoints' => $endpoints,
        ], activeNav: 'tours', title: 'Route – ' . $route->rental->eventName);
    }

    public function setProfile(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new SetRouteProfile($rentalId, $request->input('profile', '') ?? '')), 'Fahrprofil gespeichert.');
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour/route');
    }

    public function addVehicle(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new AddPlannedVehicle($rentalId, $request->input('vehicle_id', '') ?? '', $request->input('towing_vehicle_id', '') ?? '')), 'Fahrzeug geplant.');
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour/route');
    }

    public function removeVehicle(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new RemovePlannedVehicle($rentalId, $request->param('plannedId'))), 'Fahrzeug entfernt.');
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour/route');
    }

    public function editSettings(Request $request): void
    {
        $this->renderSettings([]);
    }

    public function updateSettings(Request $request): void
    {
        try {
            $this->commands->dispatch(new SaveRoutingSettings(
                $request->input('api_key', '') ?? '',
                $request->input('buffer_minutes', '') ?? '',
                $request->input('remove_key') === '1',
            ));
            Flash::success('Einstellungen der Routenplanung gespeichert.');
        } catch (ValidationException $e) {
            $this->renderSettings($e->errors());

            return;
        }
        Response::redirect('/einstellungen/routenplanung');
    }

    /** @param array<string, string> $errors */
    private function renderSettings(array $errors): void
    {
        echo View::page('settings/routing', ['settings' => $this->settings->get(), 'errors' => $errors], activeNav: 'settings-routenplanung', title: 'Routenplanung');
    }

    private function run(callable $work, string $success): void
    {
        try {
            $work();
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', array_unique($e->errors())));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
    }
}
