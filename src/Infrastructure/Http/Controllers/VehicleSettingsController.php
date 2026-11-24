<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Vehicle\Command\SaveVehicleSettings;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\VehicleSettingsRepositoryInterface;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Settings page "Fahrzeuge" (migration 181): default cost per km per category. */
final class VehicleSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly VehicleSettingsRepositoryInterface $settings,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([], []);
    }

    public function update(Request $request): void
    {
        $command = new SaveVehicleSettings(
            $request->input('default_cost_per_km_auto', '') ?? '',
            $request->input('default_cost_per_km_trailer', '') ?? '',
            $request->input('travel_cost_markup_percent', '') ?? '',
            $request->input('event_travel_costs_default') !== null,
            $request->input('travel_cost_estimate_note', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Fahrzeug-Einstellungen gespeichert.');
        } catch (ValidationException $e) {
            $this->render($e->errors(), [
                'default_cost_per_km_auto' => $command->defaultCostPerKmAuto,
                'default_cost_per_km_trailer' => $command->defaultCostPerKmTrailer,
                'travel_cost_markup_percent' => (string) $command->travelCostMarkupPercent,
                'travel_cost_estimate_note' => (string) $command->travelCostEstimateNote,
            ]);

            return;
        }

        Response::redirect('/einstellungen/fahrzeuge');
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function render(array $errors, array $old): void
    {
        echo View::page('settings/vehicles', [
            'settings' => $this->settings->get(),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-fahrzeuge', title: 'Fahrzeuge');
    }
}
