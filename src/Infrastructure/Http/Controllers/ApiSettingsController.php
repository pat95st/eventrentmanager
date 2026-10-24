<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Api\Command\CreateApiToken;
use App\Application\Api\Command\CreatedApiToken;
use App\Application\Api\Command\DeleteApiToken;
use App\Application\Api\Command\SetApiTokenEnabled;
use App\Application\Api\Command\UpdateApiToken;
use App\Application\Api\Query\ListApiTokens;
use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateApiSettings;
use App\Application\Settings\Query\GetApiSettings;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class ApiSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([], null, $request->query('bearbeiten'));
    }

    public function updateSettings(Request $request): void
    {
        $this->commands->dispatch(new UpdateApiSettings($request->input('enabled') === '1'));
        Flash::success('API-Einstellungen wurden gespeichert.');
        Response::redirect('/einstellungen/api');
    }

    public function createToken(Request $request): void
    {
        try {
            /** @var CreatedApiToken $created */
            $created = $this->commands->dispatch(new CreateApiToken(
                name: $request->input('name'),
                ipAllowlist: $request->input('ip_allowlist'),
            ));
        } catch (ValidationException $e) {
            $this->render($e->errors(), null, null);

            return;
        }

        // Der Klartext-Token ist ab jetzt für immer weg — wird per Flash EINMALIG
        // angezeigt (siehe settings/api.php), nicht in der DB gespeichert.
        $this->render([], $created->rawToken, null);
    }

    public function updateToken(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new UpdateApiToken(
                id: $id,
                name: $request->input('name'),
                ipAllowlist: $request->input('ip_allowlist'),
            ));
            Flash::success('Token wurde aktualisiert.');
            Response::redirect('/einstellungen/api');

            return;
        } catch (ValidationException $e) {
            $this->render($e->errors(), null, $id);

            return;
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/api');
    }

    public function enableToken(Request $request): void
    {
        $this->setTokenEnabled($request, true);
    }

    public function disableToken(Request $request): void
    {
        $this->setTokenEnabled($request, false);
    }

    private function setTokenEnabled(Request $request, bool $enabled): void
    {
        try {
            $this->commands->dispatch(new SetApiTokenEnabled($request->param('id'), $enabled));
            Flash::success($enabled ? 'Token wurde aktiviert.' : 'Token wurde deaktiviert.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/api');
    }

    public function deleteToken(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeleteApiToken($request->param('id')));
            Flash::success('Token wurde gelöscht.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/api');
    }

    /** @param array<string, string> $errors */
    private function render(array $errors, ?string $newRawToken, ?string $editTokenId): void
    {
        echo View::page('settings/api', [
            'apiSettings' => $this->queries->ask(new GetApiSettings()),
            'tokens' => $this->queries->ask(new ListApiTokens()),
            'errors' => $errors,
            'newRawToken' => $newRawToken,
            'editTokenId' => $editTokenId !== '' ? $editTokenId : null,
        ], activeNav: 'settings-api', title: 'API');
    }
}
