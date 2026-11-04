<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Manufacturer\Query\ListManufacturers;
use App\Application\Query\QueryBus;
use App\Application\Rack\Command\AddRackExemplar;
use App\Application\Rack\Command\AddRackSlot;
use App\Application\Rack\Command\DeleteRackExemplar;
use App\Application\Rack\Command\UpdateRackExemplar;
use App\Application\Rack\Command\CreateRack;
use App\Application\Rack\Command\DeleteRack;
use App\Application\Rack\Command\RackInput;
use App\Application\Rack\Command\RemoveRackSlot;
use App\Application\Rack\Command\UpdateRack;
use App\Application\Rack\Query\GetRack;
use App\Application\Rack\Query\GetRackByExemplar;
use App\Application\Rack\Query\ListRackDeviceOptions;
use App\Application\Rack\Query\ListRacks;
use App\Application\Rack\Query\RackDetail;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Rack-Typen und Rack-Exemplare (Migrationen 115/124), Menü Artikel › Racks. */
final class RackController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly ?\App\Infrastructure\Pdf\RackLabelPdfGenerator $labels = null,
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
            Flash::success('Rack-Typ wurde angelegt. Lege jetzt die einzelnen Exemplare an.');
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
            Flash::success('Rack-Typ wurde gespeichert.');
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
            Flash::success('Rack-Typ wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/racks');
    }

    public function addExemplar(Request $request): void
    {
        $id = $request->param('id');
        try {
            $exemplarId = $this->commands->dispatch(new AddRackExemplar($id, $request->input('acquisition_date'), $request->input('fixed_inventory_number_id')));
            Flash::success('Rack-Exemplar wurde angelegt.');
            Response::redirect('/racks/' . $id . '#exemplar-' . $exemplarId);

            return;
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/racks/' . $id);
    }

    public function updateExemplar(Request $request): void
    {
        $exemplarId = $request->param('id');
        try {
            $rackId = $this->commands->dispatch(new UpdateRackExemplar($exemplarId, $request->input('fixed_inventory_number_id'), $request->input('acquisition_date')));
            Flash::success('Rack-Exemplar wurde gespeichert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            $rackId = $this->rackIdOf($exemplarId);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/racks/' . $rackId . '#exemplar-' . $exemplarId);
    }

    public function deleteExemplar(Request $request): void
    {
        try {
            $rackId = $this->commands->dispatch(new DeleteRackExemplar($request->param('id')));
            Flash::success('Rack-Exemplar wurde gelöscht.');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/racks/' . $rackId);
    }

    public function addSlot(Request $request): void
    {
        $exemplarId = $request->param('id');
        $rackId = $this->rackIdOf($exemplarId);
        if ($rackId === null) {
            Response::notFound('Rack wurde nicht gefunden.');

            return;
        }
        try {
            $this->commands->dispatch(new AddRackSlot($exemplarId, $request->input('inventory_number_id', '') ?? '', $request->input('start_unit'), $request->input('side')));
            Flash::success('Gerät wurde eingebaut.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/racks/' . $rackId . '#exemplar-' . $exemplarId);
    }

    public function removeSlot(Request $request): void
    {
        $rackId = $request->input('rack_id', '') ?? '';
        $exemplarId = $request->input('exemplar_id', '') ?? '';
        try {
            $this->commands->dispatch(new RemoveRackSlot($request->param('id')));
            Flash::success('Gerät wurde ausgebaut.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/racks/' . $rackId . ($exemplarId !== '' ? '#exemplar-' . $exemplarId : ''));
    }

    /** Beschriftungs-PDFs je Rack-Exemplar: FRONT/REAR (A6) für die Deckel, Inhalt (A5) für oben. */
    public function labelFront(Request $request): void
    {
        $this->label($request, \App\Infrastructure\Pdf\RackLabelPdfGenerator::FRONT);
    }

    public function labelRear(Request $request): void
    {
        $this->label($request, \App\Infrastructure\Pdf\RackLabelPdfGenerator::REAR);
    }

    public function labelTop(Request $request): void
    {
        $this->label($request, \App\Infrastructure\Pdf\RackLabelPdfGenerator::TOP);
    }

    private function label(Request $request, string $kind): void
    {
        $exemplarId = $request->param('id');
        try {
            $detail = $this->queries->ask(new GetRackByExemplar($exemplarId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        $exemplar = $detail->exemplar($exemplarId);
        if ($exemplar === null || $this->labels === null) {
            Response::notFound('Rack wurde nicht gefunden.');

            return;
        }
        $pdf = $this->labels->generate($detail, $exemplar, $kind, $this->queries->ask(new \App\Application\Settings\Query\GetCompanySettings()));
        $name = 'Rack-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $exemplar->exemplar->rackNumber) . '-' . match ($kind) {
            \App\Infrastructure\Pdf\RackLabelPdfGenerator::FRONT => 'Front',
            \App\Infrastructure\Pdf\RackLabelPdfGenerator::REAR => 'Rear',
            default => 'Inhalt',
        } . '.pdf';
        Response::raw($pdf, 200, 'application/pdf', ['Content-Disposition' => 'inline; filename="' . $name . '"']);
    }

    private function rackIdOf(string $exemplarId): ?string
    {
        try {
            return $this->queries->ask(new GetRackByExemplar($exemplarId))->rack->id;
        } catch (NotFoundException) {
            return null;
        }
    }

    /** @return array<string, string> */
    private function old(Request $request): array
    {
        $old = ['_submitted' => '1'];
        foreach (['name', 'prefix', 'manufacturer_id', 'height_units', 'install_depth_cm', 'rail_type', 'width', 'color', 'description'] as $key) {
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
            prefix: $request->input('prefix', '') ?? '',
            manufacturerId: $request->input('manufacturer_id'),
            heightUnits: $request->input('height_units'),
            installDepthCm: $request->input('install_depth_cm'),
            railType: $request->input('rail_type'),
            width: $request->input('width'),
            color: $request->input('color'),
            hasShockMount: $request->input('has_shock_mount') !== null,
            hasWheels: $request->input('has_wheels') !== null,
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
