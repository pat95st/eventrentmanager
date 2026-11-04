<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateSecuritySettings;
use App\Application\Settings\Query\GetSecuritySettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Einstellungen › Sicherheit (Migration 127). Routen nur für Administratoren. */
final class SecuritySettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([], []);
    }

    public function update(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdateSecuritySettings((string) ($request->input('session_timeout_minutes') ?? '')));
            Flash::success('Sicherheits-Einstellungen wurden gespeichert.');
            Response::redirect('/einstellungen/sicherheit');
        } catch (ValidationException $e) {
            $this->render($e->errors(), $request->post);
        }
    }

    private function render(array $errors, array $old): void
    {
        echo View::page('settings/security', [
            'settings' => $this->queries->ask(new GetSecuritySettings()),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-sicherheit', title: 'Sicherheit');
    }
}
