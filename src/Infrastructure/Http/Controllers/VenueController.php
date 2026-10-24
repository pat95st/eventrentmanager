<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Rental\Query\ListRentalsForVenue;
use App\Application\Venue\Command\AddVenuePowerConnection;
use App\Application\Venue\Command\CreateVenue;
use App\Application\Venue\Command\DeleteVenue;
use App\Application\Venue\Command\DeleteVenuePowerConnection;
use App\Application\Venue\Command\UpdateVenue;
use App\Application\Venue\Command\UpdateVenuePowerConnection;
use App\Application\Settings\Query\GetCompanySettings;
use App\Application\Venue\Query\GetVenue;
use App\Application\Venue\Query\GetVenuesNeedDistributorFlags;
use App\Application\Venue\Query\ListVenuePowerConnections;
use App\Application\Venue\Query\ListVenues;
use App\Application\Warehouse\Query\ListWarehouses;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\PowerConnection;
use App\Domain\Venue\Venue;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class VenueController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListVenues($search, $page, $pageSize));

        // Warnbadge "Verteiler mit RCD nötig" für die Liste: EINE zusätzliche
        // Abfrage für alle Veranstaltungsorte der aktuellen Seite statt einer
        // pro Zeile (kein N+1), siehe GetVenuesNeedDistributorFlagsHandler.
        $venueIds = array_map(static fn (Venue $venue) => $venue->id, $result->items);
        $needsDistributorFlags = $this->queries->ask(new GetVenuesNeedDistributorFlags($venueIds));

        $viewData = [
            'venues' => $result->items,
            'needsDistributorFlags' => $needsDistributorFlags,
            'warehouses' => $this->queries->ask(new ListWarehouses())->items,
            'companyAddress' => $this->companyAddress(),
            'search' => $search ?? '',
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/veranstaltungsorte',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('venues/_table', $viewData);

            return;
        }

        echo View::page('venues/index', $viewData, activeNav: 'venues', title: t('venues.title'));
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateVenue(
            name: $request->input('name', '') ?? '',
            street: $request->input('street', '') ?? '',
            postalCode: $request->input('postal_code', '') ?? '',
            city: $request->input('city', '') ?? '',
            country: $this->nullIfEmpty($request->input('country')),
            notes: $this->nullIfEmpty($request->input('notes')),
            caretakerName: $this->nullIfEmpty($request->input('caretaker_name')),
            caretakerPhone: $this->nullIfEmpty($request->input('caretaker_phone')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('venues.flash.created'));
            Response::redirect('/veranstaltungsorte');
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $venue = $this->queries->ask(new GetVenue($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $rentalEntries = $this->queries->ask(new ListRentalsForVenue($venue->id));
        $today = new \DateTimeImmutable('today');
        $upcomingRentals = array_values(array_filter($rentalEntries, fn ($entry) => $entry->rental->endDate >= $today));
        $pastRentals = array_values(array_filter($rentalEntries, fn ($entry) => $entry->rental->endDate < $today));
        // findByVenue liefert bereits neueste zuerst (Startdatum absteigend);
        // für "kommende Veranstaltungen" ist die nächstliegende zuerst sinnvoller.
        usort($upcomingRentals, fn ($a, $b) => $a->rental->startDate <=> $b->rental->startDate);

        $powerConnections = $this->queries->ask(new ListVenuePowerConnections($venue->id));

        $this->renderForm($venue, [], [], $venue->id, $upcomingRentals, $pastRentals, $powerConnections);
    }

    /** Baut die einzeilige Firmenadresse (für den Google-Maps-Routen-Button) aus den Firmeneinstellungen. */
    private function companyAddress(): string
    {
        $companySettings = $this->queries->ask(new GetCompanySettings());

        return trim(implode(', ', array_filter([
            $companySettings->street,
            trim(($companySettings->postalCode ?? '') . ' ' . ($companySettings->city ?? '')),
        ], static fn ($part) => $part !== null && trim($part) !== '')));
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateVenue(
            id: $id,
            name: $request->input('name', '') ?? '',
            street: $request->input('street', '') ?? '',
            postalCode: $request->input('postal_code', '') ?? '',
            city: $request->input('city', '') ?? '',
            country: $this->nullIfEmpty($request->input('country')),
            notes: $this->nullIfEmpty($request->input('notes')),
            caretakerName: $this->nullIfEmpty($request->input('caretaker_name')),
            caretakerPhone: $this->nullIfEmpty($request->input('caretaker_phone')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('venues.flash.updated'));
            Response::redirect('/veranstaltungsorte');
        } catch (ValidationException $e) {
            $venue = $this->queries->ask(new GetVenue($id));
            $powerConnections = $this->queries->ask(new ListVenuePowerConnections($id));
            $this->renderForm($venue, $e->errors(), $this->oldFromCommand($command), $id, powerConnections: $powerConnections);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteVenue($id));
            Flash::success(t('venues.flash.deleted'));
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/veranstaltungsorte');
    }

    public function addPowerConnection(Request $request): void
    {
        $venueId = $request->param('id');

        $command = new AddVenuePowerConnection(
            venueId: $venueId,
            type: $request->input('type', '') ?? '',
            breakerRating: $request->input('breaker_rating', '') ?? '',
            rcdTripCurrent: $request->input('rcd_trip_current', '') ?? '',
            location: $this->nullIfEmpty($request->input('location')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('venues.power.flash.added'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungsorte/' . $venueId . '/bearbeiten');
    }

    public function updatePowerConnection(Request $request): void
    {
        $venueId = $request->param('id');
        $connectionId = $request->param('connectionId');

        $command = new UpdateVenuePowerConnection(
            id: $connectionId,
            venueId: $venueId,
            type: $request->input('type', '') ?? '',
            breakerRating: $request->input('breaker_rating', '') ?? '',
            rcdTripCurrent: $request->input('rcd_trip_current', '') ?? '',
            location: $this->nullIfEmpty($request->input('location')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('venues.power.flash.updated'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungsorte/' . $venueId . '/bearbeiten');
    }

    public function deletePowerConnection(Request $request): void
    {
        $venueId = $request->param('id');
        $connectionId = $request->param('connectionId');

        try {
            $this->commands->dispatch(new DeleteVenuePowerConnection($connectionId, $venueId));
            Flash::success(t('venues.power.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/veranstaltungsorte/' . $venueId . '/bearbeiten');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     * @param \App\Application\Rental\Query\VenueRentalEntry[] $upcomingRentals
     * @param \App\Application\Rental\Query\VenueRentalEntry[] $pastRentals
     * @param PowerConnection[] $powerConnections
     */
    private function renderForm(
        ?Venue $venue,
        array $errors,
        array $old,
        ?string $editingId = null,
        array $upcomingRentals = [],
        array $pastRentals = [],
        array $powerConnections = [],
    ): void {
        echo View::page('venues/form', [
            'venue' => $venue,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'upcomingRentals' => $upcomingRentals,
            'pastRentals' => $pastRentals,
            'powerConnections' => $powerConnections,
            'needsDistributorWithRcd' => PowerConnection::anyNeedsDistributorWithRcd($powerConnections),
            'warehouses' => $this->queries->ask(new ListWarehouses())->items,
            'companyAddress' => $this->companyAddress(),
        ], activeNav: 'venues', title: $venue !== null ? t('venues.edit') : t('venues.new'));
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateVenue|UpdateVenue $command): array
    {
        return [
            'name' => $command->name,
            'street' => $command->street,
            'postal_code' => $command->postalCode,
            'city' => $command->city,
            'country' => $command->country,
            'notes' => $command->notes,
            'caretaker_name' => $command->caretakerName,
            'caretaker_phone' => $command->caretakerPhone,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
