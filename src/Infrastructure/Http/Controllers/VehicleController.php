<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Vehicle\Command\CreateVehicle;
use App\Application\Vehicle\Command\DeleteVehicle;
use App\Application\Vehicle\Command\UpdateVehicle;
use App\Application\Vehicle\Query\GetVehicle;
use App\Application\Vehicle\Query\ListVehicles;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\Vehicle;
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
        ], activeNav: 'vehicles', title: $vehicle !== null ? 'Fahrzeug bearbeiten' : 'Neues Fahrzeug');
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
        ];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
