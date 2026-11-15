<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\PowerPlan\Command\ApplyRentalPowerSuggestions;
use App\Application\PowerPlan\Command\SaveRentalPowerPlan;
use App\Application\PowerPlan\Query\GetRentalPowerPlan;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Power planning of an event (migration 159): where each device and power
 * distributor is plugged in, expected current per phase, overloads of
 * connections, inlets and integrated protective devices, and suggestions
 * for re-plugging.
 */
final class RentalPowerPlanController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function show(Request $request): void
    {
        try {
            $view = $this->queries->ask(new GetRentalPowerPlan($request->param('id')));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('rentals/power-plan', ['powerPlan' => $view], activeNav: 'rentals', title: 'Stromplanung – ' . $view->setup->rental->eventName);
    }

    public function save(Request $request): void
    {
        $rentalId = $request->param('id');
        $rows = [];
        foreach (is_array($request->post['units'] ?? null) ? $request->post['units'] : [] as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[(string) $key] = [
                'target' => is_scalar($row['target'] ?? null) ? (string) $row['target'] : '',
                'phase' => is_scalar($row['phase'] ?? null) ? (string) $row['phase'] : '',
                'factor' => is_scalar($row['factor'] ?? null) ? (string) $row['factor'] : '',
            ];
        }

        try {
            $this->commands->dispatch(new SaveRentalPowerPlan($rentalId, $request->input('simultaneity_factor'), $rows));
            Flash::success('Stromplanung gespeichert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', array_unique($e->errors())));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/strom');
    }

    public function applySuggestions(Request $request): void
    {
        $rentalId = $request->param('id');
        $key = $request->input('key');

        try {
            $applied = (int) $this->commands->dispatch(new ApplyRentalPowerSuggestions($rentalId, $key !== null && $key !== '' ? [$key] : null));
            if ($applied > 0) {
                Flash::success($applied === 1 ? 'Vorschlag übernommen.' : $applied . ' Vorschläge übernommen.');
            } else {
                Flash::error('Der Vorschlag ist nicht mehr aktuell – bitte die neuen Vorschläge prüfen.');
            }
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/veranstaltungen/' . $rentalId . '/strom');
    }
}
