<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Manufacturer\Query\ListManufacturers;
use App\Application\Query\QueryBus;
use App\Application\Rack\Command\AddRackSlot;
use App\Application\Rack\Command\CreateRack;
use App\Application\Rack\Command\DeleteRack;
use App\Application\Rack\Command\RackInput;
use App\Application\Rack\Command\RemoveRackSlot;
use App\Application\Rack\Command\UpdateRack;
use App\Application\Rack\Query\GetRack;
use App\Application\Rack\Query\ListRackDeviceOptions;
use App\Application\Rack\Query\ListRacks;
use App\Application\Rack\Query\RackDetail;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Racks (Nutzerwunsch, Migration 115), Menü Artikel › Racks. */
final class RackController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $search = $request->query('q');
        echo View::page('racks/index', [
            'racks' => $this->queries->ask(new ListRacks($search)),
            'search' => $search ?? '',
        ], activeNav: 'racks', title: 'Racks');
    }

    public function create(Request $request): void
    {
        $this->renderForm(null, [], []);
    }

    public function store(Request $request): void
    {
        $input = $this->input($request);
        try {
            $id = $this->commands->dispatch(new CreateRack($input));
            Flash::success('Rack wurde angelegt.');
            Response::redirect('/racks/' . $id);
        } catch (ValidationException $e) {
            $this->renderForm(null, $e->errors(), $this->old($request));
        }
    }

    public function show(Request $request): void
    {
        try {
            $detail = $this->queries->ask(new GetRack($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('racks/show', [
            'detail' => $detail,
            'deviceOptions' => $this->queries->ask(new ListRackDeviceOptions()),
        ], activeNav: 'racks', title: 'Rack: ' . $detail->rack->name);
    }

    public function edit(Request $request): void
    {
        try {
            $detail = $this->queries->ask(new GetRack($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        $this->renderForm($detail, [], []);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        try {
            $this->commands->dispatch(new UpdateRack($id, $this->input($request)));
            Flash::success('Rack wurde gespeichert.');
            Response::redirect('/racks/' . $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        } catch (ValidationException $e) {
            $this->renderForm($this->queries->ask(new GetRack($id)), $e->errors(), $this->old($request));
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeleteRack($request->param('id')));
            Flash::success('Rack wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/racks');
    }

    public function addSlot(Request $request): void
    {
        $id = $request->param('id');
        try {
            $this->commands->dispatch(new AddRackSlot($id, $request->input('inventory_number_id', '') ?? '', $request->input('start_unit')));
            Flash::success('Gerät wurde eingebaut.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/racks/' . $id);
    }

    public function removeSlot(Request $request): void
    {
        $rackId = $request->input('rack_id', '') ?? '';
        try {
            $this->commands->dispatch(new RemoveRackSlot($request->param('id')));
            Flash::success('Gerät wurde ausgebaut.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/racks/' . $rackId);
    }

    /** @return array<string, string> */
    private function old(Request $request): array
    {
        $old = ['_submitted' => '1'];
        foreach (['name', 'manufacturer_id', 'height_units', 'install_depth_cm', 'rail_type', 'width', 'color', 'fixed_inventory_number_id', 'description'] as $key) {
            $old[$key] = (string) ($request->input($key) ?? '');
        }
        foreach (['has_shock_mount', 'has_wheels'] as $key) {
            if ($request->input($key) !== null) {
                $old[$key] = '1';
            }
        }

        return $old;
    }

    private function input(Request $request): RackInput
    {
        return new RackInput(
            name: $request->input('name', '') ?? '',
            manufacturerId: $request->input('manufacturer_id'),
            heightUnits: $request->input('height_units'),
            installDepthCm: $request->input('install_depth_cm'),
            railType: $request->input('rail_type'),
            width: $request->input('width'),
            color: $request->input('color'),
            hasShockMount: $request->input('has_shock_mount') !== null,
            hasWheels: $request->input('has_wheels') !== null,
            fixedInventoryNumberId: $request->input('fixed_inventory_number_id'),
            description: $request->input('description'),
        );
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderForm(?RackDetail $detail, array $errors, array $old): void
    {
        echo View::page('racks/form', [
            'detail' => $detail,
            'errors' => $errors,
            'old' => $old,
            'manufacturers' => $this->queries->ask(new ListManufacturers()),
            'deviceOptions' => $this->queries->ask(new ListRackDeviceOptions()),
        ], activeNav: 'racks', title: $detail !== null ? 'Rack bearbeiten' : 'Neues Rack');
    }
}
