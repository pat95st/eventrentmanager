<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Dashboard\ApplyDashboardTemplate;
use App\Application\Dashboard\DashboardTileCatalog;
use App\Application\Dashboard\ResetDashboardLayout;
use App\Application\Dashboard\UpdateDashboardLayout;
use App\Domain\Dashboard\DashboardTemplateRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\User;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\DashboardLayoutForm;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\t;

/**
 * /profil/dashboard: Jeder Benutzer ordnet seine Startseite selbst an oder
 * übernimmt eine Vorlage (Migration 147). Alle Aktionen wirken nur auf den
 * angemeldeten Benutzer, und angeboten wird nur, was er sehen darf (siehe
 * DashboardTileCatalog).
 */
final class DashboardSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly DashboardTemplateRepositoryInterface $templates,
    ) {
    }

    public function show(Request $request): void
    {
        $user = self::user();
        $tiles = DashboardTileCatalog::permittedFor($user);

        // Vorlagen nur mit dem Teil zeigen, den der Benutzer sehen darf;
        // bleibt davon nichts übrig, wird die Vorlage gar nicht angeboten.
        $templates = [];
        foreach ($this->templates->findAll() as $template) {
            $layout = $template->layout->only(array_keys($tiles));
            if (!$layout->isEmpty()) {
                $templates[] = ['template' => $template, 'layout' => $layout];
            }
        }

        echo View::page('profile/dashboard', [
            'tiles' => $tiles,
            'layout' => DashboardTileCatalog::layoutFor($user),
            'isCustomized' => $user->dashboardLayout !== null || $user->dashboardWidgets !== null,
            'templates' => $templates,
            'isAdmin' => $user->isAdministrator(),
        ], activeNav: 'profile', title: t('profile.dashboard.title'));
    }

    public function update(Request $request): void
    {
        $this->commands->dispatch(new UpdateDashboardLayout(self::user()->id, DashboardLayoutForm::zones($request)));

        Flash::success(t('profile.dashboard.flash.saved'));
        Response::redirect('/profil/dashboard');
    }

    public function applyTemplate(Request $request): void
    {
        try {
            $this->commands->dispatch(new ApplyDashboardTemplate(self::user()->id, $request->param('id')));
        } catch (NotFoundException) {
            Response::notFound(t('profile.dashboard.templates.not_found'));

            return;
        }

        Flash::success(t('profile.dashboard.flash.template_applied'));
        Response::redirect('/profil/dashboard');
    }

    public function reset(Request $request): void
    {
        $this->commands->dispatch(new ResetDashboardLayout(self::user()->id));

        Flash::success(t('profile.dashboard.flash.reset'));
        Response::redirect('/profil/dashboard');
    }

    private static function user(): User
    {
        return CurrentUser::get() ?? throw new \LogicException('Diese Seite ist nur angemeldet erreichbar.');
    }
}
