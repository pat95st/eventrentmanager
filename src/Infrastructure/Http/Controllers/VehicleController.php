<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Vehicle\Command\CreateVehicle;
use App\Application\Vehicle\Command\DeleteVehicle;
use App\Application\Vehicle\Command\SaveVehicleLogbookSettings;
use App\Application\Vehicle\Command\UpdateVehicle;
use App\Application\Vehicle\Query\GetVehicle;
use App\Application\Vehicle\Query\ListVehicles;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\Permission;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class VehicleController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly VehicleSettingsRepositoryInterface $vehicleSettings,
        private readonly ?UserRepositoryInterface $users = null,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        $page = Pagination::page($request);
        $pageSize = Pagination::pageSize($request, CurrentUser::get());

        $result = $this->queries->ask(new ListVehicles($search, $page, $pageSize));

        $viewData = [
            'vehicles' => $result->items,
            'vehicleSettings' => $this->vehicleSettings->get(),
            'ownerNames' => $this->ownerNames($result->items),
            'canEditVehicles' => CurrentUser::get()?->has(Permission::AusgabeRuecknahme) ?? false,
            'canManageLogbook' => CurrentUser::get()?->has(Permission::FahrtenbuchVerwalten) ?? false,
            'search' => $search ?? '',
            'page' => $result->page,
            'pageSize' => $result->pageSize,
            'totalCount' => $result->totalCount,
            'totalPages' => $result->totalPages(),
            'paginationBaseUrl' => '/fahrzeuge',
            'paginationQuery' => array_filter([
                'q' => $search ?? '',
            ], static fn ($value) => $value !== ''),
        ];

        if (Pagination::isAjax($request)) {
            echo View::render('vehicles/_table', $viewData);

            return;
        }

        echo View::page('vehicles/index', $viewData, activeNav: 'vehicles', title: 'Fahrzeuge');
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateVehicle(
            category: $request->input('category', '') ?? '',
            name: $request->input('name', '') ?? '',
            licensePlate: $this->nullIfEmpty($request->input('license_plate')),
            payloadKg: $this->nullIfEmpty($request->input('payload_kg')),
            loadAreaLengthCm: $this->nullIfEmpty($request->input('load_area_length_cm')),
            loadAreaWidthCm: $this->nullIfEmpty($request->input('load_area_width_cm')),
            loadAreaHeightCm: $this->nullIfEmpty($request->input('load_area_height_cm')),
            loadAreaOpenTop: $request->input('load_area_open_top') !== null,
            towHitchAvailable: $request->input('tow_hitch_available') !== null,
            towCapacityKg: $this->nullIfEmpty($request->input('tow_capacity_kg')),
            emptyWeightKg: $this->nullIfEmpty($request->input('empty_weight_kg')),
            notes: $this->nullIfEmpty($request->input('notes')),
            acquisitionYear: $this->nullIfEmpty($request->input('acquisition_year')),
            purchasePrice: $this->nullIfEmpty($request->input('purchase_price')),
            currentValue: $this->nullIfEmpty($request->input('current_value')),
            heightCm: $this->nullIfEmpty($request->input('height_cm')),
            widthCm: $this->nullIfEmpty($request->input('width_cm')),
            lengthCm: $this->nullIfEmpty($request->input('length_cm')),
            grossWeightKg: $this->nullIfEmpty($request->input('gross_weight_kg')),
            passengerSeats: $this->nullIfEmpty($request->input('passenger_seats')),
            costPerKm: $this->nullIfEmpty($request->input('cost_per_km')),
            isPrivate: $request->input('is_private') !== null,
            ownerUserId: $this->nullIfEmpty($request->input('owner_user_id')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Fahrzeug wurde angelegt.');
            Response::redirect('/fahrzeuge');
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $vehicle = $this->queries->ask(new GetVehicle($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderForm($vehicle, [], [], $vehicle->id);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateVehicle(
            id: $id,
            category: $request->input('category', '') ?? '',
            name: $request->input('name', '') ?? '',
            licensePlate: $this->nullIfEmpty($request->input('license_plate')),
            payloadKg: $this->nullIfEmpty($request->input('payload_kg')),
            loadAreaLengthCm: $this->nullIfEmpty($request->input('load_area_length_cm')),
            loadAreaWidthCm: $this->nullIfEmpty($request->input('load_area_width_cm')),
            loadAreaHeightCm: $this->nullIfEmpty($request->input('load_area_height_cm')),
            loadAreaOpenTop: $request->input('load_area_open_top') !== null,
            towHitchAvailable: $request->input('tow_hitch_available') !== null,
            towCapacityKg: $this->nullIfEmpty($request->input('tow_capacity_kg')),
            emptyWeightKg: $this->nullIfEmpty($request->input('empty_weight_kg')),
            notes: $this->nullIfEmpty($request->input('notes')),
            acquisitionYear: $this->nullIfEmpty($request->input('acquisition_year')),
            purchasePrice: $this->nullIfEmpty($request->input('purchase_price')),
            currentValue: $this->nullIfEmpty($request->input('current_value')),
            heightCm: $this->nullIfEmpty($request->input('height_cm')),
            widthCm: $this->nullIfEmpty($request->input('width_cm')),
            lengthCm: $this->nullIfEmpty($request->input('length_cm')),
            grossWeightKg: $this->nullIfEmpty($request->input('gross_weight_kg')),
            passengerSeats: $this->nullIfEmpty($request->input('passenger_seats')),
            costPerKm: $this->nullIfEmpty($request->input('cost_per_km')),
            isPrivate: $request->input('is_private') !== null,
            ownerUserId: $this->nullIfEmpty($request->input('owner_user_id')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Fahrzeug wurde aktualisiert.');
            Response::redirect('/fahrzeuge');
        } catch (ValidationException $e) {
            $vehicle = $this->queries->ask(new GetVehicle($id));
            $this->renderForm($vehicle, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteVehicle($id));
            Flash::success('Fahrzeug wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/fahrzeuge');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderForm(?Vehicle $vehicle, array $errors, array $old, ?string $editingId = null): void
    {
        echo View::page('vehicles/form', [
            'vehicle' => $vehicle,
            'errors' => $errors,
            'old' => $old,
            'editingId' => $editingId,
            'vehicleSettings' => $this->vehicleSettings->get(),
            'ownerOptions' => $this->ownerOptions($vehicle?->ownerUserId),
        ], activeNav: 'vehicles', title: $vehicle !== null ? 'Fahrzeug bearbeiten' : 'Neues Fahrzeug');
    }

    /** Logbook settings of a vehicle (migration 183, "Fahrtenbuch verwalten"). */
    public function logbook(Request $request): void
    {
        try {
            $vehicle = $this->queries->ask(new GetVehicle($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $this->renderLogbook($vehicle, [], []);
    }

    public function saveLogbook(Request $request): void
    {
        $id = $request->param('id');
        $command = new SaveVehicleLogbookSettings(
            vehicleId: $id,
            qrEnabled: $request->input('logbook_qr_enabled') !== null,
            anonymousAllowed: $request->input('logbook_anonymous_allowed') !== null,
            anonymousPrivateAllowed: $request->input('logbook_anonymous_private_allowed') !== null,
            anonymousRequiresApproval: $request->input('logbook_anonymous_requires_approval') !== null,
            odometerCheck: $request->input('logbook_odometer_check') !== null,
            odometerToleranceKm: $this->nullIfEmpty($request->input('logbook_odometer_tolerance_km')),
            odometerInitialKm: $this->nullIfEmpty($request->input('logbook_odometer_initial_km')),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Die Fahrtenbuch-Einstellungen wurden gespeichert.');
            Response::redirect('/fahrzeuge/' . $id . '/fahrtenbuch');
        } catch (ValidationException $e) {
            $vehicle = $this->queries->ask(new GetVehicle($id));
            $this->renderLogbook($vehicle, $e->errors(), [
                'logbook_qr_enabled' => $command->qrEnabled,
                'logbook_anonymous_allowed' => $command->anonymousAllowed,
                'logbook_anonymous_private_allowed' => $command->anonymousPrivateAllowed,
                'logbook_anonymous_requires_approval' => $command->anonymousRequiresApproval,
                'logbook_odometer_check' => $command->odometerCheck,
                'logbook_odometer_tolerance_km' => $command->odometerToleranceKm,
                'logbook_odometer_initial_km' => $command->odometerInitialKm,
            ]);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderLogbook(Vehicle $vehicle, array $errors, array $old): void
    {
        $owner = $vehicle->ownerUserId !== null ? $this->users?->findById($vehicle->ownerUserId) : null;
        echo View::page('vehicles/logbook', [
            'vehicle' => $vehicle,
            'ownerName' => $owner?->displayName(),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'vehicles', title: 'Fahrtenbuch – ' . $vehicle->name);
    }

    /**
     * Active users that can own a private vehicle, plus the current owner if
     * that account is inactive by now.
     *
     * @return array<string, string> user id => display name
     */
    private function ownerOptions(?string $currentOwnerId): array
    {
        if ($this->users === null) {
            return [];
        }
        $options = [];
        foreach ($this->users->findAllActive() as $user) {
            $options[$user->id] = $user->displayName();
        }
        if ($currentOwnerId !== null && !isset($options[$currentOwnerId])) {
            $owner = $this->users->findById($currentOwnerId);
            if ($owner !== null) {
                $options[$owner->id] = $owner->displayName() . ' (inaktiv)';
            }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * @param Vehicle[] $vehicles
     * @return array<string, string> owner user id => display name
     */
    private function ownerNames(array $vehicles): array
    {
        $names = [];
        foreach ($vehicles as $vehicle) {
            if ($vehicle->ownerUserId !== null && !isset($names[$vehicle->ownerUserId])) {
                $owner = $this->users?->findById($vehicle->ownerUserId);
                if ($owner !== null) {
                    $names[$owner->id] = $owner->displayName();
                }
            }
        }

        return $names;
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateVehicle|UpdateVehicle $command): array
    {
        return [
            'category' => $command->category,
            'name' => $command->name,
            'license_plate' => $command->licensePlate,
            'payload_kg' => $command->payloadKg,
            'load_area_length_cm' => $command->loadAreaLengthCm,
            'load_area_width_cm' => $command->loadAreaWidthCm,
            'load_area_height_cm' => $command->loadAreaHeightCm,
            'load_area_open_top' => $command->loadAreaOpenTop,
            'tow_hitch_available' => $command->towHitchAvailable,
            'tow_capacity_kg' => $command->towCapacityKg,
            'empty_weight_kg' => $command->emptyWeightKg,
            'notes' => $command->notes,
            'acquisition_year' => $command->acquisitionYear,
            'purchase_price' => $command->purchasePrice,
            'current_value' => $command->currentValue,
            'height_cm' => $command->heightCm,
            'width_cm' => $command->widthCm,
            'length_cm' => $command->lengthCm,
            'gross_weight_kg' => $command->grossWeightKg,
            'passenger_seats' => $command->passengerSeats,
            'cost_per_km' => $command->costPerKm,
            'is_private' => $command->isPrivate,
            'owner_user_id' => $command->ownerUserId,
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
