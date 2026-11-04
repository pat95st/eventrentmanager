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
use App\Domain\User\Permission;
use App\Infrastructure\Http\Auth\CurrentUser;
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
        private readonly ?\App\Domain\InventoryNumber\InventoryNumberRepositoryInterface $inventoryNumbers = null,
        // RMA-Einsätze des Geräts (ohne Kosten/Belege) auf der Prüfungsseite.
        private readonly ?\App\Domain\Rma\RmaRepositoryInterface $rmas = null,
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
            'rmas' => $this->rmas?->findByInventoryNumber($inventoryNumberId) ?? [],
            'editingInspection' => null,
            'errors' => [],
            'old' => [],
        ], activeNav: 'electrical-inspection', title: 'Elektroprüfungen: ' . $context->inventoryNumber->number);
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
            'rmas' => $this->rmas?->findByInventoryNumber($inventoryNumberId) ?? [],
            'editingInspection' => $editingInspection,
            'errors' => [],
            'old' => [],
        ], activeNav: 'electrical-inspection', title: 'Elektroprüfungen: ' . $context->inventoryNumber->number);
    }

    public function store(Request $request): void
    {
        $inventoryNumberId = $request->param('inventoryNumberId');

        $command = new CreateElectricalInspection(
            inventoryNumberId: $inventoryNumberId,
            inspectedAt: $request->input('inspected_at'),
            validityYears: $request->input('validity_years'),
            passed: $request->input('passed') !== null,
            inspector: $this->inspectorFor($request),
            protectiveConductorResistanceOhm: $request->input('protective_conductor_resistance_ohm'),
            insulationResistanceMohm: $request->input('insulation_resistance_mohm'),
            protectiveConductorCurrentMa: $request->input('protective_conductor_current_ma'),
            touchCurrentMa: $request->input('touch_current_ma'),
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

    /**
     * Prüfer: Benutzer mit dem Recht „Elektroprüfung“ (ohne Artikelverwaltung)
     * werden immer selbst eingetragen; sonst die Eingabe, leer = angemeldeter Benutzer.
     */
    private function inspectorFor(Request $request): ?string
    {
        $user = CurrentUser::get();
        $name = $user?->displayName();
        if ($user !== null && !$user->has(Permission::Artikelverwaltung)) {
            return $name;
        }
        $input = trim((string) ($request->input('inspector') ?? ''));

        return $input !== '' ? $input : $name;
    }

    /**
     * Einstieg für das Recht „Elektroprüfung“ (/elektropruefung): Gerät über
     * die Inventurnummer suchen (auch per Barcode-Scanner) und direkt zur
     * Erfassung springen.
     */
    public function search(Request $request): void
    {
        $query = trim((string) ($request->query('nummer') ?? ''));
        $notFound = false;
        if ($query !== '') {
            $inventoryNumber = $this->inventoryNumbers?->findByNumber($query) ?? $this->inventoryNumbers?->findByNumber(mb_strtoupper($query));
            if ($inventoryNumber !== null) {
                Response::redirect('/inventurnummern/' . $inventoryNumber->id . '/elektropruefungen');

                return;
            }
            $notFound = true;
        }

        $filter = trim((string) ($request->query('suche') ?? ''));

        echo View::page('electrical-inspections/search', [
            'query' => $query,
            'notFound' => $notFound,
            'filter' => $filter,
            'recent' => $this->queries->ask(new \App\Application\ElectricalInspection\Query\ListRecentElectricalInspections($filter !== '' ? $filter : null, 100)),
        ], activeNav: 'electrical-inspection', title: 'Elektroprüfung');
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
            protectiveConductorResistanceOhm: $request->input('protective_conductor_resistance_ohm'),
            insulationResistanceMohm: $request->input('insulation_resistance_mohm'),
            protectiveConductorCurrentMa: $request->input('protective_conductor_current_ma'),
            touchCurrentMa: $request->input('touch_current_ma'),
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
