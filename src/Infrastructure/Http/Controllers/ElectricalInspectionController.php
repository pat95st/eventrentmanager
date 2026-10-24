<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Article\Query\GetInventoryNumberContext;
use App\Application\Command\CommandBus;
use App\Application\ElectricalInspection\Command\CreateElectricalInspection;
use App\Application\ElectricalInspection\Command\DeleteElectricalInspection;
use App\Application\ElectricalInspection\Command\UpdateElectricalInspection;
use App\Application\ElectricalInspection\Query\GetElectricalInspection;
use App\Application\ElectricalInspection\Query\ListElectricalInspectionsForInventoryNumber;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Erfassung der Elektroprüfungswerte (DGUV V3 o. ä.) je Inventurnummer. */
final class ElectricalInspectionController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');

        try {
            $context = $this->queries->ask(new GetInventoryNumberContext($inventoryNumberId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $inspections = $this->queries->ask(new ListElectricalInspectionsForInventoryNumber($inventoryNumberId));

        echo View::page('electrical-inspections/index', [
            'context' => $context,
            'inspections' => $inspections,
            'editingInspection' => null,
            'errors' => [],
            'old' => [],
        ], activeNav: 'articles', title: 'Elektroprüfungen: ' . $context->inventoryNumber->number);
    }

    public function edit(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $id = $request->param('id');

        try {
            $context = $this->queries->ask(new GetInventoryNumberContext($inventoryNumberId));
            $editingInspection = $this->queries->ask(new GetElectricalInspection($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $inspections = $this->queries->ask(new ListElectricalInspectionsForInventoryNumber($inventoryNumberId));

        echo View::page('electrical-inspections/index', [
            'context' => $context,
            'inspections' => $inspections,
            'editingInspection' => $editingInspection,
            'errors' => [],
            'old' => [],
        ], activeNav: 'articles', title: 'Elektroprüfungen: ' . $context->inventoryNumber->number);
    }

    public function store(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');

        $command = new CreateElectricalInspection(
            inventoryNumberId: $inventoryNumberId,
            inspectedAt: $request->input('inspected_at'),
            validityYears: $request->input('validity_years'),
            passed: $request->input('passed') !== null,
            inspector: $request->input('inspector'),
            notes: $request->input('notes'),
        );

        try {
            $this->commands->dispatch($command);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/inventurnummern/' . $inventoryNumberId . '/elektropruefungen');

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Flash::success('Elektroprüfung wurde erfasst.');
        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/elektropruefungen');
    }

    public function update(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $id = $request->param('id');

        $command = new UpdateElectricalInspection(
            id: $id,
            inspectedAt: $request->input('inspected_at'),
            validityYears: $request->input('validity_years'),
            passed: $request->input('passed') !== null,
            inspector: $request->input('inspector'),
            notes: $request->input('notes'),
        );

        try {
            $this->commands->dispatch($command);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/inventurnummern/' . $inventoryNumberId . '/elektropruefungen/' . $id . '/bearbeiten');

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Flash::success('Elektroprüfung wurde aktualisiert.');
        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/elektropruefungen');
    }

    public function destroy(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteElectricalInspection($id));
            Flash::success('Elektroprüfung wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/inventurnummern/' . $inventoryNumberId . '/elektropruefungen');
    }
}
