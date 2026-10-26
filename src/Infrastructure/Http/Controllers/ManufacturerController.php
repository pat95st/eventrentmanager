<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Manufacturer\Command\CreateManufacturer;
use App\Application\Manufacturer\Command\DeleteManufacturer;
use App\Application\Manufacturer\Command\UpdateManufacturer;
use App\Application\Manufacturer\Query\ListManufacturers;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\Manufacturer;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class ManufacturerController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    /**
     * JSON-Live-Suche für das Hersteller-Auswahlfeld in Artikel-/
     * Case-Formularen (siehe App\Infrastructure\Http\renderLiveSearchField()).
     * `null`-Permission im zugehörigen `$auth->guard()`-Aufruf aus demselben
     * Grund wie bei CustomerController::search().
     */
    public function search(Request $request): void
    {
        $search = $request->query('q');
        $manufacturers = $this->queries->ask(new ListManufacturers($search, 20));

        Response::json([
            'items' => array_map(
                static fn (Manufacturer $manufacturer): array => [
                    'id' => $manufacturer->id,
                    'label' => $manufacturer->name,
                ],
                $manufacturers
            ),
        ]);
    }

    public function index(Request $request): void
    {
        $manufacturers = $this->queries->ask(new ListManufacturers());

        echo View::page('manufacturers/index', [
            'manufacturers' => $manufacturers,
            'editingManufacturer' => null,
            'errors' => [],
            'old' => [],
        ], activeNav: 'manufacturers', title: 'Hersteller');
    }

    public function edit(Request $request): void
    {
        $manufacturers = $this->queries->ask(new ListManufacturers());
        $id = $request->param('id');
        $editingManufacturer = null;
        foreach ($manufacturers as $manufacturer) {
            if ($manufacturer->id === $id) {
                $editingManufacturer = $manufacturer;
            }
        }

        if ($editingManufacturer === null) {
            Response::notFound('Dieser Hersteller wurde nicht gefunden.');
        }

        echo View::page('manufacturers/index', [
            'manufacturers' => $manufacturers,
            'editingManufacturer' => $editingManufacturer,
            'errors' => [],
            'old' => [],
        ], activeNav: 'manufacturers', title: 'Hersteller');
    }

    public function store(Request $request): void
    {
        $name = $request->input('name', '') ?? '';
        $prefix = $request->input('prefix', '') ?? '';

        try {
            $this->commands->dispatch(new CreateManufacturer($name, $prefix));
            Flash::success('Hersteller wurde angelegt.');
            Response::redirect('/hersteller');
        } catch (ValidationException $e) {
            $this->renderWithErrors($e->errors(), ['name' => $name, 'prefix' => $prefix]);
        }
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        $name = $request->input('name', '') ?? '';
        $prefix = $request->input('prefix', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateManufacturer($id, $name, $prefix));
            Flash::success('Hersteller wurde aktualisiert.');
            Response::redirect('/hersteller');
        } catch (ValidationException $e) {
            $this->renderWithErrors($e->errors(), ['name' => $name, 'prefix' => $prefix], $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteManufacturer($id));
            Flash::success('Hersteller wurde gelöscht.');
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/hersteller');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderWithErrors(array $errors, array $old, ?string $editingId = null): void
    {
        $manufacturers = $this->queries->ask(new ListManufacturers());
        $editingManufacturer = null;
        if ($editingId !== null) {
            foreach ($manufacturers as $manufacturer) {
                if ($manufacturer->id === $editingId) {
                    $editingManufacturer = $manufacturer;
                }
            }
        }

        echo View::page('manufacturers/index', [
            'manufacturers' => $manufacturers,
            'editingManufacturer' => $editingManufacturer,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'manufacturers', title: 'Hersteller');
    }
}
