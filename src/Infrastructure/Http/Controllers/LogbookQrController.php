<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Federation\LocalInstanceInterface;
use App\Application\Logbook\Command\RegenerateVehicleQrToken;
use App\Application\Logbook\Command\SaveLogbookEntry;
use App\Application\Logbook\Command\SaveLogbookEntryHandler;
use App\Application\Logbook\LogbookExternalEvents;
use App\Application\Logbook\LogbookLocationResolver;
use App\Application\Logbook\LogbookThrottle;
use App\Domain\Exception\ForbiddenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookAccess;
use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookOdometerChain;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\LogbookOdometerSession;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use DateTimeImmutable;

/**
 * QR codes of the driver's logbook (migration 185).
 *
 * /fahrt/<token> is public: logged-in drivers who may use the vehicle are
 * sent to the internal capture (with odometer check if the vehicle has
 * one), everyone else gets the capture for external drivers if the vehicle
 * allows it, otherwise the login. External drivers pass the odometer check
 * first (locked after 5 wrong readings), the capture page is protected by a
 * honeypot field and a limit of trips per hour.
 *
 * The printable QR page and a new token are for logbook managers.
 */
final class LogbookQrController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly UserRepositoryInterface $users,
        private readonly RentalRepositoryInterface $rentals,
        private readonly LogbookEntryRepositoryInterface $entries,
        private readonly LogbookLocationResolver $locations,
        private readonly LogbookExternalEvents $externalEvents,
        private readonly LogbookThrottle $throttle,
        private readonly LocalInstanceInterface $instance,
    ) {
    }

    public function show(Request $request): void
    {
        $vehicle = $this->vehicle($request);
        if ($vehicle === null) {
            return;
        }
        $user = $this->sessionUser();
        if ($user !== null && LogbookAccess::canUseVehicle($user, $vehicle)) {
            // Logged-in drivers keep the mobile pages of the QR code (qr=1).
            Response::redirect($vehicle->logbookOdometerCheck
                ? '/fahrtenbuch/fahrzeug/' . rawurlencode($vehicle->id) . '/kilometerstand?qr=1'
                : '/fahrtenbuch/neu?fahrzeug=' . rawurlencode($vehicle->id) . '&qr=1');

            return;
        }
        if ($vehicle->logbookAnonymousAllowed) {
            if ($vehicle->logbookOdometerCheck) {
                $this->renderOdometer($vehicle, null);
            } else {
                Response::redirect($this->base($vehicle) . '/erfassen');
            }

            return;
        }
        if ($user !== null) {
            $this->message($vehicle, 'Keine Berechtigung', 'Mit deinem Benutzerkonto kannst du für dieses Fahrzeug keine Fahrten erfassen.');

            return;
        }
        Session::setRedirectAfterLogin('/fahrt/' . (string) $vehicle->logbookQrToken);
        Response::redirect('/login');
    }

    public function odometer(Request $request): void
    {
        $vehicle = $this->externalVehicle($request);
        if ($vehicle === null) {
            return;
        }
        $client = $this->client();
        if ($this->throttle->odometerLockedUntil($vehicle->id, $client) !== null) {
            $this->renderOdometer($vehicle, null);

            return;
        }
        $reading = LogbookController::parseReading($request->input('odometer'));
        if ($reading !== null && (new LogbookOdometerChain($this->entries))->matches($vehicle, $reading)) {
            $this->throttle->resetOdometer($vehicle->id, $client);
            LogbookOdometerSession::remember($vehicle->id, $reading);
            Response::redirect($this->base($vehicle) . '/erfassen');

            return;
        }
        if ($reading !== null) {
            $this->throttle->registerOdometerFailure($vehicle->id, $client);
        }
        $this->renderOdometer($vehicle, $reading === null
            ? 'Bitte den aktuellen Kilometerstand als ganze Zahl angeben.'
            : 'Der Kilometerstand passt nicht zum zuletzt erfassten Stand. Bitte prüfe die Eingabe oder wende dich an das Büro.');
    }

    public function form(Request $request): void
    {
        $vehicle = $this->externalVehicle($request);
        if ($vehicle === null || !$this->odometerConfirmed($vehicle)) {
            return;
        }
        $form = ['trip_date' => (new DateTimeImmutable())->format('Y-m-d'), 'category' => LogbookCategory::Event->value];
        $confirmed = LogbookOdometerSession::confirmed($vehicle->id);
        if ($confirmed !== null) {
            $form['odometer_start'] = (string) $confirmed;
        }
        $this->renderCapture($vehicle, $form, []);
    }

    public function store(Request $request): void
    {
        $vehicle = $this->externalVehicle($request);
        if ($vehicle === null || !$this->odometerConfirmed($vehicle)) {
            return;
        }
        // Honeypot: bots fill every field; pretend success.
        if (trim((string) $request->input('website')) !== '') {
            $this->message($vehicle, 'Danke!', 'Deine Fahrt wurde gespeichert.');

            return;
        }
        $client = $this->client();
        if (!$this->throttle->allowSubmit($vehicle->id, $client)) {
            $this->message($vehicle, 'Bitte später erneut versuchen', 'Von diesem Gerät wurden in der letzten Stunde zu viele Fahrten erfasst.');

            return;
        }
        $command = new SaveLogbookEntry(
            entryId: null,
            actorUserId: null,
            tripDate: (string) $request->input('trip_date', ''),
            departTime: $request->input('depart_time'),
            arriveTime: $request->input('arrive_time'),
            driverName: $request->input('driver_name'),
            driverPhone: $request->input('driver_phone'),
            trailerId: $request->input('trailer_id'),
            category: (string) $request->input('category', ''),
            rentalId: $request->input('rental_id'),
            tourStopId: $request->input('tour_stop_id'),
            purpose: $request->input('purpose'),
            from: $request->input('from'),
            fromText: $request->input('from_text'),
            to: $request->input('to'),
            toText: $request->input('to_text'),
            roundTrip: $request->input('round_trip') !== null,
            distanceKm: $request->input('distance_km'),
            odometerStart: $request->input('odometer_start'),
            odometerEnd: $request->input('odometer_end'),
            externalVehicleId: $vehicle->id,
        );
        try {
            $this->commands->dispatch($command);
        } catch (ValidationException $e) {
            if (($e->errors()['odometerStart'] ?? null) === SaveLogbookEntryHandler::ODOMETER_MISMATCH) {
                $this->throttle->registerOdometerFailure($vehicle->id, $client);
            }
            $this->renderCapture($vehicle, $request->post, $e->errors());

            return;
        } catch (ForbiddenException $e) {
            $this->message($vehicle, 'Nicht möglich', $e->getMessage());

            return;
        }
        LogbookOdometerSession::forget($vehicle->id);
        $this->message($vehicle, 'Danke!', $vehicle->logbookAnonymousRequiresApproval
            ? 'Deine Fahrt wurde gespeichert und wird vom Büro geprüft.'
            : 'Deine Fahrt wurde gespeichert.', true);
    }

    /** JSON: events of the vehicle around a date (external capture). */
    public function events(Request $request): void
    {
        $vehicle = $this->externalVehicle($request, json: true);
        if ($vehicle === null) {
            return;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query('datum')) ?: new DateTimeImmutable('today');
        Response::json(['events' => $this->eventOptions($vehicle, $date)]);
    }

    /** JSON: locations and tour stops of an event the vehicle is planned for. */
    public function rentalOptions(Request $request): void
    {
        $vehicle = $this->externalVehicle($request, json: true);
        if ($vehicle === null) {
            return;
        }
        $rental = $this->allowedRental($vehicle, $request->param('rentalId'));
        if ($rental === null) {
            Response::json(['error' => 'Die Veranstaltung wurde nicht gefunden.'], 404);

            return;
        }
        Response::json($this->locations->rentalOptions($rental));
    }

    // --- managers -------------------------------------------------------------

    /** Printable QR code (A6) of a vehicle. */
    public function printPage(Request $request): void
    {
        $vehicle = $this->vehicles->findById($request->param('id'));
        if ($vehicle === null) {
            Response::notFound('Das Fahrzeug wurde nicht gefunden.');

            return;
        }
        if ($vehicle->logbookQrToken === null) {
            Flash::error('Für dieses Fahrzeug gibt es noch keinen QR-Code – bitte zuerst die QR-Erfassung einschalten.');
            Response::redirect('/fahrzeuge/' . $vehicle->id . '/fahrtenbuch');

            return;
        }
        echo View::render('logbook/qr-print', [
            'vehicle' => $vehicle,
            'url' => $this->instance->baseUrl() . '/fahrt/' . $vehicle->logbookQrToken,
        ]);
    }

    public function regenerate(Request $request): void
    {
        try {
            $this->commands->dispatch(new RegenerateVehicleQrToken($request->param('id')));
            Flash::success('Der QR-Code wurde neu erzeugt. Bereits gedruckte QR-Codes dieses Fahrzeugs funktionieren nicht mehr.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/fahrzeuge/' . $request->param('id') . '/fahrtenbuch');
    }

    // --- helpers ----------------------------------------------------------------

    private function vehicle(Request $request): ?Vehicle
    {
        $vehicle = $this->vehicles->findByLogbookQrToken($request->param('token'));
        if ($vehicle === null || !$vehicle->logbookQrEnabled) {
            http_response_code(404);
            echo View::render('logbook/message', ['vehicle' => null, 'title' => 'QR-Code ungültig', 'text' => 'Dieser QR-Code ist nicht (mehr) gültig. Bitte wende dich an das Büro.', 'ok' => false]);

            return null;
        }

        return $vehicle;
    }

    /** Vehicle for the external capture; null (with response) if not allowed. */
    private function externalVehicle(Request $request, bool $json = false): ?Vehicle
    {
        $vehicle = $json ? $this->vehicles->findByLogbookQrToken($request->param('token')) : $this->vehicle($request);
        if ($vehicle === null || !$vehicle->logbookQrEnabled || !$vehicle->logbookAnonymousAllowed) {
            if ($json) {
                Response::json(['error' => 'Nicht gefunden.'], 404);
            } elseif ($vehicle !== null) {
                Response::redirect($this->base($vehicle));
            }

            return null;
        }

        return $vehicle;
    }

    private function odometerConfirmed(Vehicle $vehicle): bool
    {
        if ($vehicle->logbookOdometerCheck && LogbookOdometerSession::confirmed($vehicle->id) === null) {
            Response::redirect($this->base($vehicle));

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $errors
     */
    private function renderCapture(Vehicle $vehicle, array $form, array $errors): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($form['trip_date'] ?? '')) ?: new DateTimeImmutable('today');
        $rental = $this->allowedRental($vehicle, (string) ($form['rental_id'] ?? ''), $date);
        $categories = [LogbookCategory::Event, LogbookCategory::Business];
        if ($vehicle->logbookAnonymousPrivateAllowed) {
            $categories[] = LogbookCategory::Private;
        }
        // External drivers only see warehouses and the company address besides the event locations.
        $general = array_values(array_filter($this->locations->generalOptions(), static fn (array $g) => in_array($g['group'], ['Lager', 'Firma'], true)));
        $confirmed = LogbookOdometerSession::confirmed($vehicle->id);

        echo View::render('logbook/capture', [
            'entry' => null,
            'form' => $form,
            'errors' => $errors,
            'user' => null,
            'manager' => false,
            'vehicles' => [],
            'trailers' => array_values(array_filter($this->vehicles->findByCategory(VehicleCategory::Anhaenger), static fn (Vehicle $v) => !$v->isPrivate)),
            'drivers' => [],
            'events' => $this->eventOptions($vehicle, $date),
            'generalLocations' => $general,
            'rentalLocations' => $rental !== null ? $this->locations->rentalOptions($rental) : null,
            'history' => [],
            'editableUntil' => null,
            'driverLabel' => '',
            'external' => true,
            'fixedVehicle' => $vehicle,
            'action' => $this->base($vehicle) . '/erfassen',
            'apiBase' => $this->base($vehicle),
            'allowedCategories' => $categories,
            'verifiedOdometer' => $confirmed !== null && (string) $confirmed === (string) ($form['odometer_start'] ?? '') ? $confirmed : null,
        ]);
    }

    private function renderOdometer(Vehicle $vehicle, ?string $error): void
    {
        echo View::render('logbook/odometer', [
            'vehicle' => $vehicle,
            'action' => $this->base($vehicle) . '/kilometerstand',
            'gapAction' => null,
            'error' => $error,
            'gap' => null,
            'lockedUntil' => $this->throttle->odometerLockedUntil($vehicle->id, $this->client()),
            'who' => '',
            'backUrl' => null,
        ]);
    }

    private function message(?Vehicle $vehicle, string $title, string $text, bool $ok = false): void
    {
        echo View::render('logbook/message', [
            'vehicle' => $vehicle,
            'title' => $title,
            'text' => $text,
            'ok' => $ok,
            'againUrl' => $ok && $vehicle !== null ? $this->base($vehicle) : null,
        ]);
    }

    /** @return list<array{id: string, label: string, isTour: bool}> */
    private function eventOptions(Vehicle $vehicle, DateTimeImmutable $date): array
    {
        return array_map(static fn (Rental $r) => [
            'id' => $r->id,
            'label' => ($r->isTour ? '🚚 ' : '') . $r->eventName . ' (' . $r->startDate->format('d.m.') . ($r->endDate->format('Y-m-d') !== $r->startDate->format('Y-m-d') ? '–' . $r->endDate->format('d.m.') : '') . ')',
            'isTour' => $r->isTour,
        ], $this->externalEvents->forVehicle($vehicle->id, $date));
    }

    private function allowedRental(Vehicle $vehicle, string $rentalId, ?DateTimeImmutable $date = null): ?Rental
    {
        if ($rentalId === '') {
            return null;
        }
        $date ??= new DateTimeImmutable('today');
        foreach ([$date, new DateTimeImmutable('today')] as $day) {
            if ($this->externalEvents->allows($vehicle->id, $day, $rentalId)) {
                return $this->rentals->findById($rentalId);
            }
        }

        return null;
    }

    private function sessionUser(): ?User
    {
        $id = Session::userId();
        $user = $id !== null ? $this->users->findById($id) : null;

        return $user !== null && $user->isActive ? $user : null;
    }

    private function client(): string
    {
        return 'ip:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    private function base(Vehicle $vehicle): string
    {
        return '/fahrt/' . rawurlencode((string) $vehicle->logbookQrToken);
    }
}
