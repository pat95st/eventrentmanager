<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Routing\Command\AddPlannedVehicle;
use App\Application\Routing\Command\RemovePlannedVehicle;
use App\Application\Routing\Command\SaveRoutingSettings;
use App\Application\Routing\Command\SetLegBilling;
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
        private readonly ?\App\Domain\Rental\RentalRepositoryInterface $rentals = null,
        private readonly ?\App\Application\Routing\RoutingClientInterface $client = null,
    ) {
    }

    /**
     * Page shell; the route itself (geocoding, OpenRouteService) is loaded
     * afterwards from data() so the page appears at once with a spinner.
     */
    public function show(Request $request): void
    {
        $rentalId = $request->param('id');
        $rental = $this->rentals?->findById($rentalId);
        if ($rental === null) {
            Response::notFound('Die Veranstaltung wurde nicht gefunden.');

            return;
        }
        if (!$rental->isTour) {
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
            'rental' => $rental,
            'profile' => \App\Domain\Routing\RouteProfile::tryFrom($rental->routeProfile) ?? \App\Domain\Routing\RouteProfile::Car,
            'planned' => $planned,
            'vehicles' => $this->vehicles->findAll(),
            'endpoints' => $endpoints,
        ], activeNav: 'tours', title: 'Route – ' . $rental->eventName);
    }

    /** JSON with the calculated route (route page and timing warnings of the tour page). */
    public function data(Request $request): void
    {
        try {
            $route = $this->queries->ask(new GetTourRoute($request->param('id')));
        } catch (NotFoundException $e) {
            Response::json(['error' => $e->getMessage()], 404);

            return;
        }
        $result = $route->result;
        Response::json([
            'profile' => $route->profile->label(),
            'restrictions' => $route->restrictions->describe(),
            'straightLine' => $result?->straightLine ?? true,
            'totalDistanceM' => $result?->totalDistanceM(),
            'totalDurationS' => $result?->totalDurationS(),
            'billableDistanceM' => $route->billableDistanceM(),
            'points' => array_map(static fn ($p) => ['label' => $p->label, 'lat' => $p->latitude, 'lng' => $p->longitude, 'kind' => $p->kind], $route->points),
            'legs' => $result !== null ? array_map(static fn ($l) => [
                'from' => $route->points[$l->from]->label ?? '',
                'to' => $route->points[$l->to]->label ?? '',
                'distanceM' => $l->distanceM,
                'durationS' => $l->durationS,
                'geometry' => $l->geometry,
                'billed' => $route->isLegBilled($l),
                // stop whose arrival this leg is; null = default destination warehouse (cannot be marked)
                'stopId' => $route->points[$l->to]->stopId ?? null,
            ], $result->legs) : [],
            'entries' => $route->entries,
            'error' => $route->error,
            'hasApiKey' => $route->hasApiKey,
            'hints' => $route->hints,
            'timingWarnings' => $route->timingWarnings,
            'googleLinks' => $route->googleLinks,
        ]);
    }

    public function setProfile(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new SetRouteProfile($rentalId, $request->input('profile', '') ?? '')), 'Fahrprofil gespeichert.');
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour/route');
    }

    /** Marks the drive to a stop as (not) billed; JSON for the route page, redirect otherwise. */
    public function setLegBilling(Request $request): void
    {
        $billed = $request->input('billed') === '1';
        try {
            $rentalId = $this->commands->dispatch(new SetLegBilling($request->param('stopId'), $billed));
        } catch (NotFoundException $e) {
            Response::json(['error' => $e->getMessage()], 404);

            return;
        } catch (ValidationException $e) {
            Response::json(['error' => implode(' ', $e->errors())], 422);

            return;
        }
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            Response::json(['billed' => $billed]);

            return;
        }
        Flash::success($billed ? 'Die Fahrt wird abgerechnet.' : 'Die Fahrt wird nicht abgerechnet.');
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

    /** Settings page: calculates a short test route (Hamburg → Hannover) with the stored key. */
    public function testSettings(Request $request): void
    {
        $settings = $this->settings->get();
        if (!$settings->hasApiKey() || $this->client === null) {
            Flash::error('Bitte zuerst einen API-Schlüssel speichern.');
            Response::redirect('/einstellungen/routenplanung');

            return;
        }
        try {
            $result = $this->client->route(
                (string) $settings->apiKey,
                [new \App\Domain\Routing\RoutePoint('Hamburg', 53.5511, 9.9937, 'start'), new \App\Domain\Routing\RoutePoint('Hannover', 52.3759, 9.7320, 'end')],
                \App\Domain\Routing\RouteProfile::Car,
                new \App\Domain\Routing\VehicleRestrictions(),
            );
            Flash::success(sprintf(
                'Verbindung zu OpenRouteService funktioniert: Testroute Hamburg → Hannover %s km, %d min.',
                number_format($result->totalDistanceM() / 1000, 0, ',', '.'),
                (int) round((float) $result->totalDurationS() / 60),
            ));
        } catch (\App\Application\Routing\RoutingException $e) {
            Flash::error('Test fehlgeschlagen: ' . $e->getMessage());
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
