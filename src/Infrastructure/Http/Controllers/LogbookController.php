<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Logbook\Command\DeleteLogbookEntry;
use App\Application\Logbook\Command\ReportOdometerGap;
use App\Application\Logbook\Command\ReviewLogbookEntry;
use App\Application\Logbook\Command\RestoreLogbookEntry;
use App\Application\Logbook\Command\SaveLogbookEntry;
use App\Application\Logbook\Command\SaveLogbookSettings;
use App\Application\Logbook\LogbookDistanceService;
use App\Application\Logbook\LogbookLocationResolver;
use App\Application\Logbook\LogbookThrottle;
use App\Domain\Exception\ForbiddenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookAccess;
use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookEntry;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookFilter;
use App\Domain\Logbook\LogbookOdometerChain;
use App\Domain\Logbook\LogbookSettingsRepositoryInterface;
use App\Domain\Logbook\LogbookStatus;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\TechnicianAccess;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleCategory;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\LogbookOdometerSession;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use DateTimeImmutable;

/**
 * Driver's logbook (migration 184): list with filters and CSV export (app
 * layout), the mobile capture page (standalone, like the audio live page)
 * for recording and editing trips, JSON helpers of the capture page and the
 * global settings.
 */
final class LogbookController
{
    /** Events offered on the capture page: trip date +/- this many days. */
    private const EVENT_WINDOW_DAYS = 3;

    /** @var array<string, ?User> */
    private array $userCache = [];
    /** @var array<string, ?Rental> */
    private array $rentalCache = [];

    public function __construct(
        private readonly CommandBus $commands,
        private readonly LogbookEntryRepositoryInterface $entries,
        private readonly LogbookSettingsRepositoryInterface $settings,
        private readonly UserRepositoryInterface $users,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly RentalRepositoryInterface $rentals,
        private readonly LogbookLocationResolver $locations,
        private readonly LogbookDistanceService $distances,
        private readonly ?RentalTechnicianRepositoryInterface $rentalTechnicians = null,
        private readonly ?LogbookThrottle $throttle = null,
    ) {
    }

    public function index(Request $request): void
    {
        $user = $this->user();
        $manager = LogbookAccess::canManage($user);
        [$filter, $query] = $this->filterFromRequest($request, $user, $manager);

        if ($request->query('export') === 'csv') {
            $csv = $this->csv($this->entries->search($filter));
            Response::download($csv, 'Fahrtenbuch-' . date('Y-m-d') . '.csv', 'text/csv; charset=UTF-8');

            return;
        }

        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, $user);
        $totalCount = $this->entries->count($filter);
        $entries = $this->entries->search($filter, $pageSize, ($page - 1) * $pageSize);

        $viewData = [
            'entries' => $entries,
            'totals' => $this->entries->totals($filter),
            'pendingCount' => $manager ? $this->entries->count(new LogbookFilter(status: LogbookStatus::Pending)) : 0,
            'query' => $query,
            'manager' => $manager,
            'settings' => $this->settings->get(),
            'vehicleNames' => $this->vehicleNames(),
            'vehicleOptions' => $this->vehicles->findAll(),
            'driverOptions' => $manager ? $this->driverOptions() : [],
            'names' => $this->entryNames($entries),
            'currentUser' => $user,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalCount' => $totalCount,
            'totalPages' => max(1, (int) ceil($totalCount / $pageSize)),
            'paginationBaseUrl' => '/fahrtenbuch',
            'paginationQuery' => $query,
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('logbook/_table', $viewData);

            return;
        }

        echo View::page('logbook/index', $viewData, activeNav: 'logbook', title: 'Fahrtenbuch');
    }

    public function create(Request $request): void
    {
        $user = $this->user();
        $rental = $this->rentalParam($request->query('veranstaltung'));
        $form = [
            'trip_date' => (new DateTimeImmutable())->format('Y-m-d'),
            'category' => LogbookCategory::Event->value,
            'vehicle_id' => (string) $request->query('fahrzeug', ''),
            'rental_id' => $rental?->id ?? '',
        ];
        // Reading confirmed on the odometer check page (migration 185).
        if ($form['vehicle_id'] !== '') {
            $confirmed = LogbookOdometerSession::confirmed($form['vehicle_id']);
            if ($confirmed !== null) {
                $form['odometer_start'] = (string) $confirmed;
            }
        }
        if ($rental !== null) {
            $form['return_to_rental'] = $rental->id;
            $template = $request->query('vorlage');
            $options = $this->locations->rentalOptions($rental);
            if ($template === 'hin' || $template === 'rueck') {
                $leg = $template === 'hin' ? $options['outbound'] : $options['return'];
                $form['from'] = (string) $leg['from'];
                $form['to'] = (string) $leg['to'];
                $form['trip_date'] = min($leg['date'], (new DateTimeImmutable('tomorrow'))->format('Y-m-d'));
            }
        }

        $this->renderCapture(null, $form, [], $user);
    }

    public function store(Request $request): void
    {
        $user = $this->user();
        try {
            $this->commands->dispatch($this->commandFromRequest($request, null, $user));
            LogbookOdometerSession::forget((string) $request->input('vehicle_id'));
            Flash::success('Die Fahrt wurde gespeichert.');
            $rentalId = $request->input('return_to_rental');
            Response::redirect($rentalId !== null && $rentalId !== '' ? '/veranstaltungen/' . rawurlencode($rentalId) : '/fahrtenbuch');
        } catch (ValidationException $e) {
            $this->renderCapture(null, $request->post, $e->errors(), $user);
        } catch (ForbiddenException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/fahrtenbuch');
        }
    }

    public function edit(Request $request): void
    {
        $user = $this->user();
        $entry = $this->editableEntry($request->param('id'), $user);
        if ($entry === null) {
            return;
        }
        $this->renderCapture($entry, $this->formFromEntry($entry), [], $user);
    }

    public function update(Request $request): void
    {
        $user = $this->user();
        $entry = $this->editableEntry($request->param('id'), $user);
        if ($entry === null) {
            return;
        }
        try {
            $this->commands->dispatch($this->commandFromRequest($request, $entry->id, $user));
            Flash::success('Die Fahrt wurde geändert.');
            Response::redirect('/fahrtenbuch');
        } catch (ValidationException $e) {
            $this->renderCapture($entry, $request->post, $e->errors(), $user);
        } catch (ForbiddenException|NotFoundException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/fahrtenbuch');
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeleteLogbookEntry($request->param('id'), $this->user()->id));
            Flash::success('Die Fahrt wurde gelöscht.');
        } catch (ForbiddenException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/fahrtenbuch');
    }

    public function restore(Request $request): void
    {
        try {
            $this->commands->dispatch(new RestoreLogbookEntry($request->param('id'), $this->user()->id));
            Flash::success('Die Fahrt wurde wiederhergestellt.');
        } catch (ForbiddenException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/fahrtenbuch?geloescht=1');
    }

    public function approve(Request $request): void
    {
        $this->review($request, true);
    }

    public function reject(Request $request): void
    {
        $this->review($request, false);
    }

    private function review(Request $request, bool $approve): void
    {
        try {
            $this->commands->dispatch(new ReviewLogbookEntry($request->param('id'), $this->user()->id, $approve));
            Flash::success($approve ? 'Die Fahrt wurde freigegeben.' : 'Die Fahrt wurde abgelehnt; sie zählt nicht für die Abrechnung.');
        } catch (ForbiddenException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        $back = (string) $request->input('back');
        Response::redirect(str_starts_with($back, '/fahrtenbuch') ? $back : '/fahrtenbuch');
    }

    /** Odometer check page of a vehicle for logged-in drivers (migration 185). */
    public function odometerForm(Request $request): void
    {
        $vehicle = $this->checkedVehicle($request->param('id'));
        if ($vehicle !== null) {
            $this->renderOdometer($vehicle, null, null);
        }
    }

    public function odometerCheck(Request $request): void
    {
        $vehicle = $this->checkedVehicle($request->param('id'));
        if ($vehicle === null) {
            return;
        }
        $client = 'user:' . $this->user()->id;
        $locked = $this->throttle?->odometerLockedUntil($vehicle->id, $client);
        if ($locked !== null) {
            $this->renderOdometer($vehicle, null, null, $locked);

            return;
        }
        $reading = self::parseReading($request->input('odometer'));
        if ($reading === null) {
            $this->renderOdometer($vehicle, 'Bitte den aktuellen Kilometerstand als ganze Zahl angeben.', null);

            return;
        }
        $chain = new LogbookOdometerChain($this->entries);
        if ($chain->matches($vehicle, $reading)) {
            $this->throttle?->resetOdometer($vehicle->id, $client);
            LogbookOdometerSession::remember($vehicle->id, $reading);
            Response::redirect('/fahrtenbuch/neu?fahrzeug=' . rawurlencode($vehicle->id));

            return;
        }
        $locked = $this->throttle?->registerOdometerFailure($vehicle->id, $client);
        $this->renderOdometer(
            $vehicle,
            'Der Kilometerstand passt nicht zum zuletzt erfassten Stand.',
            $chain->isGap($vehicle, $reading) ? ['expected' => (int) $chain->expected($vehicle), 'reading' => $reading] : null,
            $locked,
        );
    }

    public function reportGap(Request $request): void
    {
        $vehicle = $this->checkedVehicle($request->param('id'));
        if ($vehicle === null) {
            return;
        }
        $reading = self::parseReading($request->input('odometer'));
        try {
            $this->commands->dispatch(new ReportOdometerGap($vehicle->id, $this->user()->id, (int) $reading));
        } catch (ValidationException|ForbiddenException $e) {
            $this->renderOdometer($vehicle, $e instanceof ValidationException ? implode(' ', $e->errors()) : $e->getMessage(), null);

            return;
        }
        $this->throttle?->resetOdometer($vehicle->id, 'user:' . $this->user()->id);
        LogbookOdometerSession::remember($vehicle->id, (int) $reading);
        Flash::success('Die Lücke wurde gemeldet; ein Fahrtenbuch-Verwalter klärt sie. Du kannst jetzt deine Fahrt erfassen.');
        Response::redirect('/fahrtenbuch/neu?fahrzeug=' . rawurlencode($vehicle->id));
    }

    private function checkedVehicle(string $id): ?Vehicle
    {
        $vehicle = $this->vehicles->findById($id);
        if ($vehicle === null || !LogbookAccess::canUseVehicle($this->user(), $vehicle)) {
            Response::notFound('Das Fahrzeug wurde nicht gefunden.');

            return null;
        }
        if (!$vehicle->logbookOdometerCheck) {
            Response::redirect('/fahrtenbuch/neu?fahrzeug=' . rawurlencode($vehicle->id));

            return null;
        }

        return $vehicle;
    }

    /** @param array{expected: int, reading: int}|null $gap */
    private function renderOdometer(Vehicle $vehicle, ?string $error, ?array $gap, ?DateTimeImmutable $lockedUntil = null): void
    {
        echo View::render('logbook/odometer', [
            'vehicle' => $vehicle,
            'action' => '/fahrtenbuch/fahrzeug/' . $vehicle->id . '/kilometerstand',
            'gapAction' => '/fahrtenbuch/fahrzeug/' . $vehicle->id . '/luecke',
            'error' => $error,
            'gap' => $gap,
            'lockedUntil' => $lockedUntil,
            'who' => $this->user()->displayName(),
            'backUrl' => '/fahrtenbuch',
        ]);
    }

    public static function parseReading(?string $value): ?int
    {
        $value = trim(str_replace(['.', ' '], '', (string) $value));
        if (preg_match('/^\d{1,7}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    /** JSON: events around a date for the capture page. */
    public function events(Request $request): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query('datum')) ?: new DateTimeImmutable('today');
        Response::json(['events' => $this->eventOptions($this->user(), $date, $request->query('veranstaltung'))]);
    }

    /** JSON: start/destination choices and templates of an event. */
    public function rentalOptions(Request $request): void
    {
        $rental = $this->rentalParam($request->param('id'));
        if ($rental === null) {
            Response::json(['error' => 'Die Veranstaltung wurde nicht gefunden.'], 404);

            return;
        }
        Response::json($this->locations->rentalOptions($rental));
    }

    /** JSON: suggested one-way driving distance between two choices. */
    public function distance(Request $request): void
    {
        $rentalId = $request->query('veranstaltung');
        $rentalId = $rentalId !== null && $rentalId !== '' && $this->rentalParam($rentalId) !== null ? $rentalId : null;
        try {
            $from = $this->locations->resolve($request->query('von'), $request->query('von_text'), $rentalId, 'from');
            $to = $this->locations->resolve($request->query('nach'), $request->query('nach_text'), $rentalId, 'to');
        } catch (ValidationException $e) {
            Response::json(['km' => null, 'hint' => 'Bitte Start und Ziel wählen.']);

            return;
        }
        if ($from === null || $to === null) {
            Response::json(['km' => null, 'hint' => 'Bitte Start und Ziel wählen.']);

            return;
        }
        Response::json($this->distances->suggest($from, $to));
    }

    public function settingsForm(Request $request): void
    {
        echo View::page('settings/logbook', ['settings' => $this->settings->get(), 'errors' => [], 'old' => []], activeNav: 'settings-fahrtenbuch', title: 'Fahrtenbuch');
    }

    public function saveSettings(Request $request): void
    {
        try {
            $this->commands->dispatch(new SaveLogbookSettings($request->input('edit_window_days')));
            Flash::success('Die Fahrtenbuch-Einstellungen wurden gespeichert.');
            Response::redirect('/einstellungen/fahrtenbuch');
        } catch (ValidationException $e) {
            echo View::page('settings/logbook', [
                'settings' => $this->settings->get(),
                'errors' => $e->errors(),
                'old' => ['edit_window_days' => $request->input('edit_window_days')],
            ], activeNav: 'settings-fahrtenbuch', title: 'Fahrtenbuch');
        }
    }

    // --- capture page -----------------------------------------------------

    /**
     * @param array<string, mixed> $form values by input name
     * @param array<string, string> $errors
     */
    private function renderCapture(?LogbookEntry $entry, array $form, array $errors, User $user): void
    {
        $manager = LogbookAccess::canManage($user);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($form['trip_date'] ?? '')) ?: new DateTimeImmutable('today');
        $rentalId = (string) ($form['rental_id'] ?? '');
        $rental = $rentalId !== '' ? $this->rentalParam($rentalId) : null;
        $settings = $this->settings->get();

        echo View::render('logbook/capture', [
            'entry' => $entry,
            'form' => $form,
            'errors' => $errors,
            'user' => $user,
            'manager' => $manager,
            'vehicles' => array_values(array_filter($this->vehicles->findByCategory(VehicleCategory::Auto), static fn (Vehicle $v) => LogbookAccess::canUseVehicle($user, $v) || $v->id === $entry?->vehicleId)),
            'trailers' => array_values(array_filter($this->vehicles->findByCategory(VehicleCategory::Anhaenger), static fn (Vehicle $v) => LogbookAccess::canUseVehicle($user, $v) || $v->id === $entry?->trailerId)),
            'drivers' => $manager ? $this->driverOptions($entry?->driverUserId) : [],
            'events' => $this->eventOptions($user, $date, $rental?->id),
            'generalLocations' => $this->locations->generalOptions(),
            'rentalLocations' => $rental !== null ? $this->locations->rentalOptions($rental) : null,
            'history' => $entry !== null ? $this->entries->findChanges($entry->id) : [],
            'editableUntil' => $entry !== null && !$manager ? $entry->createdAt->modify('+' . $settings->editWindowDays . ' days') : null,
            'driverLabel' => $entry !== null ? $this->driverLabel($entry) : $user->displayName(),
            'external' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function formFromEntry(LogbookEntry $entry): array
    {
        $location = static fn (?\App\Domain\Logbook\LogbookLocation $l): string => $l === null ? '' : $l->type->value . ':' . ($l->type === \App\Domain\Logbook\LogbookLocationType::Custom ? '' : (string) $l->refId);
        $odometer = $entry->odometerStart !== null && $entry->odometerEnd !== null;

        return [
            'trip_date' => $entry->tripDate->format('Y-m-d'),
            'depart_time' => $entry->departTime,
            'arrive_time' => $entry->arriveTime,
            'driver_user_id' => $entry->isExternal ? 'external' : (string) $entry->driverUserId,
            'driver_name' => $entry->driverName,
            'driver_phone' => $entry->driverPhone,
            'vehicle_id' => $entry->vehicleId ?? ($entry->otherVehicleLabel !== null ? 'other' : ''),
            'other_vehicle_label' => $entry->otherVehicleLabel,
            'trailer_id' => $entry->trailerId,
            'category' => $entry->category->value,
            'rental_id' => $entry->rentalId,
            'tour_stop_id' => $entry->tourStopId,
            'purpose' => $entry->purpose,
            'from' => $location($entry->from),
            'from_text' => $entry->from?->type === \App\Domain\Logbook\LogbookLocationType::Custom ? $entry->from->label : '',
            'to' => $location($entry->to),
            'to_text' => $entry->to?->type === \App\Domain\Logbook\LogbookLocationType::Custom ? $entry->to->label : '',
            'round_trip' => $entry->roundTrip ? '1' : null,
            'distance_km' => $odometer ? '' : (string) ($entry->roundTrip ? intdiv($entry->distanceKm, 2) : $entry->distanceKm),
            'odometer_start' => $entry->odometerStart,
            'odometer_end' => $entry->odometerEnd,
            'not_billed' => $entry->notBilled ? '1' : null,
        ];
    }

    private function commandFromRequest(Request $request, ?string $entryId, User $user): SaveLogbookEntry
    {
        return new SaveLogbookEntry(
            entryId: $entryId,
            actorUserId: $user->id,
            tripDate: (string) $request->input('trip_date', ''),
            departTime: $request->input('depart_time'),
            arriveTime: $request->input('arrive_time'),
            driverUserId: $request->input('driver_user_id'),
            driverName: $request->input('driver_name'),
            driverPhone: $request->input('driver_phone'),
            vehicleId: $request->input('vehicle_id'),
            otherVehicleLabel: $request->input('other_vehicle_label'),
            trailerId: $request->input('trailer_id'),
            category: (string) $request->input('category', ''),
            rentalId: $request->input('rental_id'),
            tourStopId: $request->input('tour_stop_id'),
            purpose: $request->input('purpose'),
            from: $request->input('from'),
            fromText: $request->input('from_text'),
            saveFromAddress: $request->input('save_from_address') !== null,
            to: $request->input('to'),
            toText: $request->input('to_text'),
            saveToAddress: $request->input('save_to_address') !== null,
            roundTrip: $request->input('round_trip') !== null,
            distanceKm: $request->input('distance_km'),
            odometerStart: $request->input('odometer_start'),
            odometerEnd: $request->input('odometer_end'),
            notBilled: LogbookAccess::canManage($user) ? $request->input('not_billed') !== null : null,
            bypassOdometerCheck: LogbookAccess::canManage($user) && $request->input('bypass_odometer_check') !== null,
        );
    }

    private function editableEntry(string $id, User $user): ?LogbookEntry
    {
        $entry = $this->entries->findById($id);
        if ($entry === null || !LogbookAccess::canView($user, $entry)) {
            Response::notFound('Die Fahrt wurde nicht gefunden.');

            return null;
        }
        if ($entry->isDeleted() || !LogbookAccess::canEdit($user, $entry, $this->settings->get())) {
            Flash::error($entry->isDeleted() ? 'Die Fahrt ist gelöscht.' : 'Diese Fahrt kann nicht mehr geändert werden – bitte wende dich an einen Fahrtenbuch-Verwalter.');
            Response::redirect('/fahrtenbuch');

            return null;
        }

        return $entry;
    }

    /**
     * Events around the trip date (+ the given one). Technicians who only
     * see their own events get only the events they are booked for.
     *
     * @return list<array{id: string, label: string, isTour: bool}>
     */
    private function eventOptions(User $user, DateTimeImmutable $date, ?string $includeId): array
    {
        $from = $date->modify('-' . self::EVENT_WINDOW_DAYS . ' days');
        $to = $date->modify('+' . self::EVENT_WINDOW_DAYS . ' days')->setTime(23, 59, 59);
        $rentals = [];
        foreach ($this->rentals->findOverlapping($from, $to) as $rental) {
            $rentals[$rental->id] = $rental;
        }
        if ($includeId !== null && $includeId !== '' && !isset($rentals[$includeId])) {
            $included = $this->rentals->findById($includeId);
            if ($included !== null) {
                $rentals[$included->id] = $included;
            }
        }
        if (TechnicianAccess::isRestricted($user)) {
            $allowed = [];
            foreach ($this->rentalTechnicians?->findByUser($user->id) ?? [] as $assignment) {
                if (TechnicianAccess::canSeeRental($assignment)) {
                    $allowed[$assignment->rentalId] = true;
                }
            }
            $rentals = array_intersect_key($rentals, $allowed);
        }
        usort($rentals, static fn (Rental $a, Rental $b) => [$a->startDate, $a->eventName] <=> [$b->startDate, $b->eventName]);

        return array_map(static fn (Rental $r) => [
            'id' => $r->id,
            'label' => ($r->isTour ? '🚚 ' : '') . $r->eventName . ' (' . $r->startDate->format('d.m.') . ($r->endDate->format('Y-m-d') !== $r->startDate->format('Y-m-d') ? '–' . $r->endDate->format('d.m.') : '') . ')',
            'isTour' => $r->isTour,
        ], $rentals);
    }

    private function rentalParam(?string $id): ?Rental
    {
        if ($id === null || $id === '') {
            return null;
        }
        $rental = $this->rentals->findById($id);
        if ($rental === null) {
            return null;
        }
        $user = CurrentUser::get();
        if ($user !== null && TechnicianAccess::isRestricted($user)) {
            $assignment = null;
            foreach ($this->rentalTechnicians?->findByUser($user->id) ?? [] as $candidate) {
                if ($candidate->rentalId === $rental->id) {
                    $assignment = $candidate;
                }
            }
            if (!TechnicianAccess::canSeeRental($assignment)) {
                return null;
            }
        }

        return $rental;
    }

    // --- list -------------------------------------------------------------

    /** @return array{0: LogbookFilter, 1: array<string, string>} */
    private function filterFromRequest(Request $request, User $user, bool $manager): array
    {
        $query = [];
        $get = static function (string $key) use ($request, &$query): ?string {
            $value = trim((string) $request->query($key, ''));
            if ($value === '') {
                return null;
            }
            $query[$key] = $value;

            return $value;
        };
        $date = static fn (?string $v): ?DateTimeImmutable => $v !== null ? (DateTimeImmutable::createFromFormat('!Y-m-d', $v) ?: null) : null;

        $from = $date($get('von'));
        $to = $date($get('bis'));
        $vehicle = $get('fahrzeug');
        $driver = $manager ? $get('fahrer') : null;
        $category = LogbookCategory::tryFrom((string) $get('kategorie'));
        $rental = $get('veranstaltung');
        $externalRaw = $manager ? $get('extern') : null;
        $status = $manager ? LogbookStatus::tryFrom((string) $get('status')) : null;
        $deleted = $manager && $get('geloescht') === '1';

        return [new LogbookFilter(
            from: $from,
            to: $to,
            vehicleId: $vehicle,
            driverUserId: $driver,
            category: $category,
            rentalId: $rental,
            external: $externalRaw === null ? null : $externalRaw === '1',
            status: $status,
            ownUserId: $manager ? null : $user->id,
            includeDeleted: $deleted,
        ), $query];
    }

    /** @param LogbookEntry[] $entries */
    private function csv(array $entries): string
    {
        $names = $this->entryNames($entries);
        $vehicles = $this->vehicleNames();
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Datum', 'Abfahrt', 'Ankunft', 'Fahrer', 'Extern', 'Fahrzeug', 'Anhänger', 'Kategorie', 'Veranstaltung', 'Zweck', 'Start', 'Ziel', 'Hin- und Rückfahrt', 'km', 'Km-Stand Abfahrt', 'Km-Stand Ankunft', 'Status', 'Nicht abrechnen', 'Gelöscht'], ';', '"', '');
        foreach ($entries as $entry) {
            fputcsv($handle, [
                $entry->tripDate->format('d.m.Y'),
                $entry->departTime ?? '',
                $entry->arriveTime ?? '',
                $names['drivers'][$entry->id] ?? '',
                $entry->isExternal ? 'ja' : 'nein',
                $entry->vehicleId !== null ? ($vehicles[$entry->vehicleId] ?? '') : (string) $entry->otherVehicleLabel,
                $entry->trailerId !== null ? ($vehicles[$entry->trailerId] ?? '') : '',
                $entry->category->label(),
                $names['rentals'][$entry->id] ?? '',
                $entry->purpose ?? '',
                $entry->from?->display() ?? '',
                $entry->to?->display() ?? '',
                $entry->roundTrip ? 'ja' : 'nein',
                (string) $entry->distanceKm,
                $entry->odometerStart !== null ? (string) $entry->odometerStart : '',
                $entry->odometerEnd !== null ? (string) $entry->odometerEnd : '',
                $entry->status->label(),
                $entry->notBilled ? 'ja' : 'nein',
                $entry->isDeleted() ? 'ja' : 'nein',
            ], ';', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    // --- names --------------------------------------------------------------

    /**
     * Driver and event names per entry id.
     *
     * @param LogbookEntry[] $entries
     * @return array{drivers: array<string, string>, rentals: array<string, string>}
     */
    private function entryNames(array $entries): array
    {
        $names = ['drivers' => [], 'rentals' => []];
        foreach ($entries as $entry) {
            $names['drivers'][$entry->id] = $this->driverLabel($entry);
            if ($entry->rentalId !== null) {
                $rental = $this->rentalCache[$entry->rentalId] ??= $this->rentals->findById($entry->rentalId);
                if ($rental !== null) {
                    $names['rentals'][$entry->id] = $rental->eventName;
                }
            }
        }

        return $names;
    }

    private function driverLabel(LogbookEntry $entry): string
    {
        if ($entry->driverUserId !== null) {
            $driver = $this->userCache[$entry->driverUserId] ??= $this->users->findById($entry->driverUserId);
            if ($driver !== null) {
                return $driver->displayName();
            }
        }

        return (string) ($entry->driverName ?? 'Unbekannt');
    }

    /** @return array<string, string> vehicle id => "name (plate)" */
    private function vehicleNames(): array
    {
        $names = [];
        foreach ($this->vehicles->findAll() as $vehicle) {
            $names[$vehicle->id] = $vehicle->name . ($vehicle->licensePlate !== null ? ' (' . $vehicle->licensePlate . ')' : '');
        }

        return $names;
    }

    /** @return array<string, string> user id => name: active users with a logbook right (+ the current driver) */
    private function driverOptions(?string $includeId = null): array
    {
        $options = [];
        foreach ($this->users->findAllActive() as $user) {
            if (LogbookAccess::canCapture($user) || $user->id === $includeId) {
                $options[$user->id] = $user->displayName();
            }
        }
        if ($includeId !== null && !isset($options[$includeId])) {
            $driver = $this->users->findById($includeId);
            if ($driver !== null) {
                $options[$driver->id] = $driver->displayName() . ' (inaktiv)';
            }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    private function user(): User
    {
        $user = CurrentUser::get();
        assert($user !== null);

        return $user;
    }
}
