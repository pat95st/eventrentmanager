<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateConfirmSettings;
use App\Application\Settings\Command\UpdateSecuritySettings;
use App\Application\Settings\Query\GetSecuritySettings;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ConfirmCatalog;
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

    /** Which confirmations holding Shift may skip (migration 174). */
    public function updateConfirmations(Request $request): void
    {
        $reset = $request->input('reset') !== null;
        $required = $request->post['required'] ?? [];
        $this->commands->dispatch(new UpdateConfirmSettings(
            bypassEnabled: $request->input('confirm_bypass_enabled') !== null,
            requiredKeys: is_array($required) ? array_values(array_filter($required, 'is_string')) : [],
            reset: $reset,
        ));
        Flash::success($reset ? 'Die Rückfragen wurden auf den Standard zurückgesetzt.' : 'Die Einstellungen zu Rückfragen wurden gespeichert.');
        Response::redirect('/einstellungen/sicherheit#rueckfragen');
    }

    private function render(array $errors, array $old): void
    {
        echo View::page('settings/security', [
            'settings' => $this->queries->ask(new GetSecuritySettings()),
            'errors' => $errors,
            'old' => $old,
            'confirmGroups' => ConfirmCatalog::grouped(),
        ], activeNav: 'settings-sicherheit', title: 'Sicherheit');
    }
}
