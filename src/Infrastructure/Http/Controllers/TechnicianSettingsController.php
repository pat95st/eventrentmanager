<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Technician\Command\DeleteTrade;
use App\Application\Technician\Command\SaveTechnicianSettings;
use App\Application\Technician\Command\SaveTrade;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Technician\TechnicianSettingsRepositoryInterface;
use App\Domain\Technician\TradeRepositoryInterface;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Einstellungen › Lager & Veranstaltungen › Techniker & Gewerke (Migration 134). */
final class TechnicianSettingsController
{
    private const URL = '/einstellungen/techniker';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly TradeRepositoryInterface $trades,
        private readonly TechnicianSettingsRepositoryInterface $settings,
    ) {
    }

    public function index(Request $request): void
    {
        echo View::page('settings/technicians', [
            'trades' => $this->trades->findAll(),
            'settings' => $this->settings->get(),
        ], activeNav: 'settings-techniker', title: 'Techniker & Gewerke');
    }

    public function storeTrade(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveTrade(null, $request->input('name', '') ?? '', $request->input('sort_order', '0') ?? '0')), 'Das Gewerk wurde angelegt.');
    }

    public function updateTrade(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveTrade($request->param('id'), $request->input('name', '') ?? '', $request->input('sort_order', '0') ?? '0')), 'Das Gewerk wurde gespeichert.');
    }

    public function deleteTrade(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new DeleteTrade($request->param('id'))), 'Das Gewerk wurde gelöscht.');
    }

    public function saveSettings(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveTechnicianSettings($request->input('reminder_hours', '') ?? '')), 'Die Erinnerungen wurden gespeichert.');
    }

    private function run(callable $action, string $success): void
    {
        try {
            $action();
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect(self::URL);
    }
}
