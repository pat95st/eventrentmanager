<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Dashboard\DashboardTileCatalog;
use App\Application\Dashboard\DeleteDashboardTemplate;
use App\Application\Dashboard\SaveDashboardTemplate;
use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\DashboardTemplate;
use App\Domain\Dashboard\DashboardTemplateRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\DashboardLayoutForm;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\t;

/**
 * /einstellungen/dashboard-vorlagen (nur Administration): Vorlagen für die
 * Startseite pflegen, die sich Benutzer unter /profil/dashboard übernehmen
 * können (Migration 147). Eine Vorlage darf jede Kachel enthalten; wer sie
 * übernimmt, bekommt nur die Kacheln, für die er das Recht hat.
 */
final class DashboardTemplateController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly DashboardTemplateRepositoryInterface $templates,
    ) {
    }

    public function index(Request $request): void
    {
        echo View::page('settings/dashboard-templates/index', [
            'templates' => $this->templates->findAll(),
            'tiles' => DashboardTileCatalog::all(),
        ], activeNav: 'settings-dashboard-vorlagen', title: t('settings.dashboard_templates.title'));
    }

    public function create(Request $request): void
    {
        // Ausgangspunkt ist die eigene Startseite des Administrators – so
        // lässt sich eine Anordnung erst ausprobieren und dann als Vorlage
        // ablegen.
        $user = CurrentUser::get();
        $layout = $user !== null ? DashboardTileCatalog::layoutFor($user) : DashboardTileCatalog::defaultLayout();

        $this->renderForm(null, '', '', $layout, []);
    }

    public function store(Request $request): void
    {
        $this->save(null, $request);
    }

    public function edit(Request $request): void
    {
        $template = $this->find($request);

        $this->renderForm($template, $template->name, $template->description ?? '', $template->layout, []);
    }

    public function update(Request $request): void
    {
        $this->save($this->find($request), $request);
    }

    public function destroy(Request $request): void
    {
        $this->commands->dispatch(new DeleteDashboardTemplate($request->param('id')));

        Flash::success(t('settings.dashboard_templates.flash.deleted'));
        Response::redirect('/einstellungen/dashboard-vorlagen');
    }

    private function save(?DashboardTemplate $template, Request $request): void
    {
        $name = (string) $request->input('name', '');
        $description = (string) $request->input('description', '');
        $zones = DashboardLayoutForm::zones($request);

        try {
            $this->commands->dispatch(new SaveDashboardTemplate($template?->id, $name, $description, $zones));
        } catch (ValidationException $e) {
            $this->renderForm($template, $name, $description, DashboardLayout::fromZones($zones), $e->errors());

            return;
        } catch (NotFoundException) {
            Response::notFound();

            return;
        }

        Flash::success(t('settings.dashboard_templates.flash.saved'));
        Response::redirect('/einstellungen/dashboard-vorlagen');
    }

    private function find(Request $request): DashboardTemplate
    {
        $template = $this->templates->findById($request->param('id'));
        if ($template === null) {
            Response::notFound();
            exit;
        }

        return $template;
    }

    /** @param array<string, string> $errors */
    private function renderForm(?DashboardTemplate $template, string $name, string $description, DashboardLayout $layout, array $errors): void
    {
        $tiles = DashboardTileCatalog::all();

        echo View::page('settings/dashboard-templates/form', [
            'template' => $template,
            'name' => $name,
            'description' => $description,
            'tiles' => $tiles,
            'layout' => $layout->only(array_keys($tiles)),
            'errors' => $errors,
        ], activeNav: 'settings-dashboard-vorlagen', title: t('settings.dashboard_templates.title'));
    }
}
