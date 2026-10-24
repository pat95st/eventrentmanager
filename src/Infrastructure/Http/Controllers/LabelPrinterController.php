<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Printing\Command\CreateLabelPrinter;
use App\Application\Printing\Command\DeleteLabelPrinter;
use App\Application\Printing\Command\SendTestPrint;
use App\Application\Printing\Command\UpdateLabelPrinter;
use App\Application\Printing\Query\ListLabelPrinters;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Printing\LabelPrinter;
use App\Infrastructure\Printing\LabelPrinterDriverRegistry;
use App\Infrastructure\Printing\PrinterConnectionException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/** Admin-Verwaltung der konfigurierten Etikettendrucker (siehe LabelPrinterDriverRegistry für die Treiberauswahl). */
final class LabelPrinterController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $this->render(null, [], []);
    }

    public function create(Request $request): void
    {
        $this->render(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateLabelPrinter(
            name: $request->input('name', '') ?? '',
            driverType: $request->input('driver_type', '') ?? '',
            ipAddress: $request->input('ip_address', '') ?? '',
            port: $request->input('port', '') ?? '',
            tapeWidthMm: $request->input('tape_width_mm', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.label_printers.flash.created'));
            Response::redirect('/einstellungen/etikettendrucker');
        } catch (ValidationException $e) {
            $this->render(null, $e->errors(), $this->oldFromCommand($command));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');
        $printers = $this->queries->ask(new ListLabelPrinters());
        $editing = $this->findInList($printers, $id);

        if ($editing === null) {
            Response::notFound(t('settings.label_printers.not_found'));

            return;
        }

        $this->render($editing, [], []);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateLabelPrinter(
            id: $id,
            name: $request->input('name', '') ?? '',
            driverType: $request->input('driver_type', '') ?? '',
            ipAddress: $request->input('ip_address', '') ?? '',
            port: $request->input('port', '') ?? '',
            tapeWidthMm: $request->input('tape_width_mm', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.label_printers.flash.updated'));
            Response::redirect('/einstellungen/etikettendrucker');
        } catch (ValidationException $e) {
            $printers = $this->queries->ask(new ListLabelPrinters());
            $editing = $this->findInList($printers, $id);
            $this->render($editing, $e->errors(), $this->oldFromCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteLabelPrinter($id));
            Flash::success(t('settings.label_printers.flash.deleted'));
        } catch (ConflictException|NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/etikettendrucker');
    }

    /** Sendet sofort ein kleines Testetikett — fängt Verbindungsfehler ab und zeigt eine verständliche deutsche Meldung statt eines rohen Fehlers. */
    public function testPrint(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new SendTestPrint($id));
            Flash::success(t('settings.label_printers.flash.test_sent'));
        } catch (PrinterConnectionException $e) {
            Flash::error($e->getMessage());
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/etikettendrucker');
    }

    /** @param LabelPrinter[] $printers */
    private function findInList(array $printers, string $id): ?LabelPrinter
    {
        foreach ($printers as $printer) {
            if ($printer->id === $id) {
                return $printer;
            }
        }

        return null;
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function render(?LabelPrinter $editing, array $errors, array $old, ?string $editingId = null): void
    {
        $printers = $this->queries->ask(new ListLabelPrinters());

        echo View::page('settings/label-printers/index', [
            'printers' => $printers,
            'editing' => $editing,
            'errors' => $errors,
            'old' => $old,
            'availableDrivers' => LabelPrinterDriverRegistry::availableDrivers(),
            'availableTapeWidths' => [12, 24, 29],
        ], activeNav: 'settings-etikettendrucker', title: t('settings.label_printers.title'));
    }

    /** @return array<string, mixed> */
    private function oldFromCommand(CreateLabelPrinter|UpdateLabelPrinter $command): array
    {
        return [
            'name' => $command->name,
            'driver_type' => $command->driverType,
            'ip_address' => $command->ipAddress,
            'port' => $command->port,
            'tape_width_mm' => $command->tapeWidthMm,
        ];
    }
}
