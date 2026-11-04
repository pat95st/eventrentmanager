<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Cable\Command\DeleteCableType;
use App\Application\Cable\Command\DeleteConnectorType;
use App\Application\Cable\Command\SaveCableType;
use App\Application\Cable\Command\SaveConnectorType;
use App\Application\Cable\Query\GetCableSettings;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Einstellungen › Leitungen (Migration 125): Leitungstypen, Steckverbinder, erlaubte Kombinationen. */
final class CableSettingsController
{
    private const URL = '/einstellungen/leitungen';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        echo View::page('settings/cables', [
            'settings' => $this->queries->ask(new GetCableSettings()),
        ], activeNav: 'settings-leitungen', title: 'Leitungen');
    }

    public function saveType(Request $request): void
    {
        $id = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new SaveCableType(
            id: $id !== '' ? $id : null,
            name: $request->input('name', '') ?? '',
            description: $request->input('description'),
            requiresElectricalInspection: $request->input('requires_electrical_inspection') !== null,
            protectionClass: $request->input('protection_class'),
            connectorIds: $request->inputArray('connector_ids'),
            sortOrder: $request->input('sort_order'),
        )), 'Der Leitungstyp wurde gespeichert.', '#typ-' . $id);
    }

    public function createType(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveCableType(
            id: null,
            name: $request->input('name', '') ?? '',
            description: $request->input('description'),
            requiresElectricalInspection: $request->input('requires_electrical_inspection') !== null,
            protectionClass: $request->input('protection_class'),
            connectorIds: $request->inputArray('connector_ids'),
            sortOrder: $request->input('sort_order'),
        )), 'Der Leitungstyp wurde angelegt.', '#leitungstypen');
    }

    public function deleteType(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new DeleteCableType($request->param('id'))), 'Der Leitungstyp wurde gelöscht.', '#leitungstypen');
    }

    public function createConnector(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveConnectorType(
            id: null,
            name: $request->input('connector_name', '') ?? '',
            sortOrder: $request->input('sort_order'),
            allowForCableTypeIds: $request->inputArray('cable_type_ids'),
        )), 'Der Steckverbinder wurde angelegt.', '#steckverbinder');
    }

    public function saveConnector(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveConnectorType(
            id: $request->param('id'),
            name: $request->input('connector_name', '') ?? '',
            sortOrder: $request->input('sort_order'),
        )), 'Der Steckverbinder wurde gespeichert.', '#steckverbinder');
    }

    public function deleteConnector(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new DeleteConnectorType($request->param('id'))), 'Der Steckverbinder wurde gelöscht.', '#steckverbinder');
    }

    private function run(callable $action, string $success, string $anchor): void
    {
        try {
            $action();
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException | NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect(self::URL . $anchor);
    }
}
