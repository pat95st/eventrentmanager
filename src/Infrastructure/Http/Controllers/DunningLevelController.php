<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Dunning\Command\CreateDunningLevel;
use App\Application\Dunning\Command\DeleteDunningLevel;
use App\Application\Dunning\Command\UpdateDunningLevel;
use App\Application\Dunning\Command\UpdateDunningSettings;
use App\Application\Dunning\Query\GetDunningSettings;
use App\Application\Dunning\Query\ListDunningLevels;
use App\Application\Query\QueryBus;
use App\Domain\Dunning\DunningLevel;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\t;

/**
 * Verwaltung der Mahnstufen (Stammdaten-Liste, analog
 * LaborTimeArticleController) UND der Mahnwesen-Einstellung
 * "automatisch versenden" (DunningSettings) auf derselben Seite — bewusst
 * kombiniert, da beides zusammengehört und keine eigene Unterseite
 * rechtfertigt.
 */
final class DunningLevelController
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

    public function edit(Request $request): void
    {
        $id = $request->param('id');
        $levels = $this->queries->ask(new ListDunningLevels());
        $editingLevel = null;
        foreach ($levels as $level) {
            if ($level->id === $id) {
                $editingLevel = $level;
            }
        }

        if ($editingLevel === null) {
            Response::notFound('Diese Mahnstufe wurde nicht gefunden.');

            return;
        }

        $this->render($editingLevel, [], []);
    }

    public function store(Request $request): void
    {
        $name = $request->input('name', '') ?? '';
        $daysAfterDue = $request->input('days_after_due', '') ?? '';
        $feeType = $request->input('fee_type', '') ?? '';
        $feeValue = $request->input('fee_value', '') ?? '';

        try {
            $this->commands->dispatch(new CreateDunningLevel($name, $daysAfterDue, $feeType, $feeValue));
            Flash::success(t('settings.dunning_levels.flash.created'));
            Response::redirect('/einstellungen/mahnwesen');
        } catch (ValidationException $e) {
            $this->render(null, $e->errors(), ['name' => $name, 'days_after_due' => $daysAfterDue, 'fee_type' => $feeType, 'fee_value' => $feeValue]);
        }
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        $name = $request->input('name', '') ?? '';
        $daysAfterDue = $request->input('days_after_due', '') ?? '';
        $feeType = $request->input('fee_type', '') ?? '';
        $feeValue = $request->input('fee_value', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateDunningLevel($id, $name, $daysAfterDue, $feeType, $feeValue));
            Flash::success(t('settings.dunning_levels.flash.updated'));
            Response::redirect('/einstellungen/mahnwesen');
        } catch (ValidationException $e) {
            $levels = $this->queries->ask(new ListDunningLevels());
            $editingLevel = null;
            foreach ($levels as $level) {
                if ($level->id === $id) {
                    $editingLevel = $level;
                }
            }
            $this->render($editingLevel, $e->errors(), ['name' => $name, 'days_after_due' => $daysAfterDue, 'fee_type' => $feeType, 'fee_value' => $feeValue]);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteDunningLevel($id));
            Flash::success(t('settings.dunning_levels.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/mahnwesen');
    }

    public function updateSettings(Request $request): void
    {
        $autoSendEnabled = $request->input('auto_send_enabled') === '1';

        $this->commands->dispatch(new UpdateDunningSettings($autoSendEnabled));
        Flash::success(t('settings.dunning_levels.flash.settings_updated'));
        Response::redirect('/einstellungen/mahnwesen');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function render(?DunningLevel $editingLevel, array $errors, array $old): void
    {
        $levels = $this->queries->ask(new ListDunningLevels());
        $dunningSettings = $this->queries->ask(new GetDunningSettings());

        echo View::page('settings/dunning-levels/index', [
            'levels' => $levels,
            'editingLevel' => $editingLevel,
            'dunningSettings' => $dunningSettings,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-mahnwesen', title: t('settings.dunning_levels.title'));
    }
}
