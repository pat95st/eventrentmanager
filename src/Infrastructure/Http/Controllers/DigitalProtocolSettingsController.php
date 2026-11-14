<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\DigitalStagebox\Command\DeleteDigitalProtocol;
use App\Application\DigitalStagebox\Command\SaveDigitalProtocol;
use App\Application\DigitalStagebox\Query\GetDigitalStageboxSettings;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Einstellungen › Digitale Protokolle (Migration 152): Protokolle für digitale Stageboxen. */
final class DigitalProtocolSettingsController
{
    private const URL = '/einstellungen/digitale-protokolle';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        echo View::page('settings/digital-protocols', [
            'settings' => $this->queries->ask(new GetDigitalStageboxSettings()),
        ], activeNav: 'settings-digitale-protokolle', title: 'Digitale Protokolle');
    }

    public function create(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveDigitalProtocol(
            id: null,
            name: $request->input('name', '') ?? '',
            sortOrder: $request->input('sort_order'),
        )), 'Das digitale Protokoll wurde angelegt.');
    }

    public function save(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveDigitalProtocol(
            id: $request->param('id'),
            name: $request->input('name', '') ?? '',
            sortOrder: $request->input('sort_order'),
        )), 'Das digitale Protokoll wurde gespeichert.');
    }

    public function delete(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new DeleteDigitalProtocol($request->param('id'))), 'Das digitale Protokoll wurde gelöscht.');
    }

    private function run(callable $action, string $success): void
    {
        try {
            $action();
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException | NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect(self::URL);
    }
}
