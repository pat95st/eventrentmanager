<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Api\Command\CreateApiToken;
use App\Application\Api\Command\DeleteApiToken;
use App\Application\Api\Command\SetApiTokenEnabled;
use App\Application\Api\Command\UpdateApiToken;
use App\Application\Api\Query\ListApiTokens;
use App\Application\Command\CommandBus;
use App\Application\OAuth\OAuthService;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateApiSettings;
use App\Application\Settings\Query\GetApiSettings;
use App\Domain\Api\ApiRequestLogRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ApiSettingsRepositoryInterface;
use App\Infrastructure\Http\Api\RateLimit\RateLimiterUnavailable;
use App\Infrastructure\Http\Api\RateLimit\RedisClient;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use DateTimeImmutable;

/**
 * Einstellungen › API: global switch, rate limiting (database or Redis),
 * tokens with scopes/read-only/rate limit and the log of write requests.
 */
final class ApiSettingsController
{
    private const LOG_ENTRIES = 100;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly ApiRequestLogRepositoryInterface $requestLog,
        private readonly ApiSettingsRepositoryInterface $settings,
        private readonly ?OAuthService $oauth = null,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([], null, $request->query('bearbeiten'));
    }

    public function updateSettings(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdateApiSettings(
                enabled: $request->input('enabled') === '1',
                rateLimitPerMinute: $request->input('rate_limit_per_minute'),
                rateLimitBackend: $request->input('rate_limit_backend'),
                redisConnection: $request->input('redis_connection'),
                redisHost: $request->input('redis_host'),
                redisPort: $request->input('redis_port'),
                redisSocketPath: $request->input('redis_socket_path'),
                redisUsername: $request->input('redis_username'),
                redisPassword: $request->input('redis_password'),
                clearRedisPassword: $request->input('redis_password_clear') === '1',
                redisDatabase: $request->input('redis_database'),
                redisKeyPrefix: $request->input('redis_key_prefix'),
                redisTimeoutMs: $request->input('redis_timeout_ms'),
                mcpEnabled: $request->input('mcp_enabled') === '1',
                mcpReadOnly: $request->input('mcp_read_only') === '1',
            ));
        } catch (ValidationException $e) {
            $this->render($e->errors(), null, null);

            return;
        }
        Flash::success('API-Einstellungen wurden gespeichert.');
        Response::redirect('/einstellungen/api');
    }

    /** Tests the saved Redis connection (PING + server version). */
    public function testRedis(Request $request): void
    {
        $settings = $this->settings->get();
        try {
            $version = RedisClient::fromSettings($settings)->ping();
            $this->settings->recordRedisError(null, new DateTimeImmutable());
            Flash::success('Verbindung zu Redis erfolgreich (' . RedisClient::address($settings) . ', Redis ' . $version . ').');
        } catch (RateLimiterUnavailable $e) {
            $this->settings->recordRedisError($e->getMessage(), new DateTimeImmutable());
            Flash::error('Redis ist nicht erreichbar: ' . $e->getMessage());
        }
        Response::redirect('/einstellungen/api');
    }

    public function createToken(Request $request): void
    {
        try {
            $created = $this->commands->dispatch(new CreateApiToken(
                name: $request->input('name'),
                ipAllowlist: $request->input('ip_allowlist'),
                scopes: $request->inputArray('scopes'),
                readOnly: $request->input('read_only') === '1',
                rateLimitPerMinute: $request->input('rate_limit_per_minute'),
                mcpAllowed: $request->input('mcp_allowed') === '1',
            ));
        } catch (ValidationException $e) {
            $this->render($e->errors(), null, null);

            return;
        }

        // Der Klartext-Token ist ab jetzt für immer weg — wird EINMALIG
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
                scopes: $request->inputArray('scopes'),
                readOnly: $request->input('read_only') === '1',
                rateLimitPerMinute: $request->input('rate_limit_per_minute'),
                mcpAllowed: $request->input('mcp_allowed') === '1',
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
            'requestLog' => $this->requestLog->latest(self::LOG_ENTRIES),
            'errors' => $errors,
            'newRawToken' => $newRawToken,
            'editTokenId' => $editTokenId !== '' ? $editTokenId : null,
            'connections' => $this->oauth?->connections(null) ?? [],
        ], activeNav: 'settings-api', title: 'API');
    }
}
