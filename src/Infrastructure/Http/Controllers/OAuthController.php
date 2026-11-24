<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\OAuth\AuthorizationRequest;
use App\Application\OAuth\OAuthError;
use App\Application\OAuth\OAuthService;
use App\Domain\Api\ApiScope;
use App\Domain\OAuth\OAuthScopePolicy;
use App\Domain\Settings\ApiSettingsRepositoryInterface;
use App\Infrastructure\Http\Api\ApiEmitter;
use App\Infrastructure\Http\Api\ApiKernel;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Mcp\McpServer;
use Closure;
use DateTimeImmutable;
use JsonException;

use function App\Infrastructure\Http\t;

/**
 * HTTP side of the OAuth 2.1 authorization server for /mcp (see OAuthService):
 *
 * - GET  /.well-known/oauth-protected-resource[/mcp]  (RFC 9728)
 * - GET  /.well-known/oauth-authorization-server      (RFC 8414)
 * - POST /oauth/register  dynamic client registration (RFC 7591)
 * - GET  /oauth/authorize consent page after the normal login (incl. 2FA)
 * - POST /oauth/authorize decision of the user (session + CSRF)
 * - POST /oauth/token     authorization_code / refresh_token
 * - POST /oauth/revoke    (RFC 7009)
 * - Profile › KI-Assistenten and Settings › API: list and disconnect.
 *
 * Every endpoint answers 404 while the REST API or the MCP server is off.
 * The machine endpoints send CORS headers (no cookies are involved) and are
 * rate limited per client IP.
 */
final class OAuthController
{
    private const REGISTER_LIMIT_PER_MINUTE = 10;
    private const TOKEN_LIMIT_PER_MINUTE = 60;
    private const CORS_HEADERS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Authorization, Content-Type, MCP-Protocol-Version, Mcp-Session-Id',
        'Access-Control-Expose-Headers' => 'WWW-Authenticate',
        'Access-Control-Max-Age' => '86400',
    ];

    /** @param (Closure(): DateTimeImmutable)|null $clock */
    public function __construct(
        private readonly OAuthService $oauth,
        private readonly ApiSettingsRepositoryInterface $settings,
        private readonly ApiKernel $kernel,
        private readonly ?Closure $clock = null,
    ) {
    }

    // --- URLs ------------------------------------------------------------------

    /** @param array<string, mixed> $server */
    public static function issuer(array $server): string
    {
        return McpServer::origin($server);
    }

    /** @param array<string, mixed> $server */
    public static function resource(array $server): string
    {
        return self::issuer($server) . McpServer::PATH;
    }

    /**
     * WWW-Authenticate value for 401 answers of /mcp, pointing clients to the
     * protected resource metadata (MCP authorization, RFC 9728 section 5.1).
     *
     * @param array<string, mixed> $server
     */
    public static function challenge(array $server): string
    {
        return 'Bearer resource_metadata="' . self::issuer($server) . '/.well-known/oauth-protected-resource"';
    }

    /** Adds the CORS headers to /mcp and OAuth responses. */
    public static function withCors(ApiResponse $response): ApiResponse
    {
        return $response->withHeaders(self::CORS_HEADERS);
    }

    /** Router handler emitting the ApiResponse of a machine endpoint. @param Closure(Request): ApiResponse $action */
    public static function emitting(Closure $action): callable
    {
        return static function (Request $request) use ($action): void {
            ApiEmitter::emit(self::withCors($action($request)));
        };
    }

    // --- Metadata -----------------------------------------------------------

    /** @param array<string, mixed> $server */
    public function protectedResourceMetadata(array $server): ApiResponse
    {
        if (!$this->available()) {
            return ApiResponse::error(404, 'Nicht gefunden.');
        }

        return ApiResponse::json([
            'resource' => self::resource($server),
            'authorization_servers' => [self::issuer($server)],
            'scopes_supported' => self::supportedScopes(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'Materialverleih',
            'resource_documentation' => self::issuer($server) . '/profil/ki-assistenten',
        ]);
    }

    /** @param array<string, mixed> $server */
    public function authorizationServerMetadata(array $server): ApiResponse
    {
        if (!$this->available()) {
            return ApiResponse::error(404, 'Nicht gefunden.');
        }
        $issuer = self::issuer($server);

        return ApiResponse::json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/oauth/authorize',
            'token_endpoint' => $issuer . '/oauth/token',
            'registration_endpoint' => $issuer . '/oauth/register',
            'revocation_endpoint' => $issuer . '/oauth/revoke',
            'scopes_supported' => self::supportedScopes(),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'revocation_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
            'authorization_response_iss_parameter_supported' => true,
        ]);
    }

    // --- Machine endpoints ------------------------------------------------------

    /** @param array<string, mixed> $server */
    public function register(array $server, string $rawBody): ApiResponse
    {
        if (!$this->available()) {
            return ApiResponse::error(404, 'Nicht gefunden.');
        }
        $limited = $this->limit('register', $server, self::REGISTER_LIMIT_PER_MINUTE);
        if ($limited !== null) {
            return $limited;
        }
        try {
            $metadata = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ApiResponse::json(['error' => 'invalid_client_metadata', 'error_description' => 'The body must be a JSON object.'], 400);
        }
        if (!is_array($metadata) || array_is_list($metadata)) {
            return ApiResponse::json(['error' => 'invalid_client_metadata', 'error_description' => 'The body must be a JSON object.'], 400);
        }
        try {
            return ApiResponse::json($this->oauth->registerClient($metadata), 201);
        } catch (OAuthError $e) {
            return ApiResponse::json($e->body(), $e->status);
        }
    }

    /**
     * @param array<string, mixed> $params form parameters
     * @param array<string, mixed> $server
     */
    public function token(array $params, array $server): ApiResponse
    {
        if (!$this->available()) {
            return ApiResponse::error(404, 'Nicht gefunden.');
        }
        $limited = $this->limit('token', $server, self::TOKEN_LIMIT_PER_MINUTE);
        if ($limited !== null) {
            return $limited;
        }
        try {
            return ApiResponse::json($this->oauth->token($params, self::basicAuth($server), self::resource($server)))
                ->withHeaders(['Pragma' => 'no-cache']);
        } catch (OAuthError $e) {
            $response = ApiResponse::json($e->body(), $e->status);

            return $e->status === 401 ? $response->withHeaders(['WWW-Authenticate' => 'Basic realm="oauth"']) : $response;
        }
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $server
     */
    public function revoke(array $params, array $server): ApiResponse
    {
        if (!$this->available()) {
            return ApiResponse::error(404, 'Nicht gefunden.');
        }
        $limited = $this->limit('token', $server, self::TOKEN_LIMIT_PER_MINUTE);
        if ($limited !== null) {
            return $limited;
        }
        $this->oauth->revoke(is_string($params['token'] ?? null) ? $params['token'] : '');

        return ApiResponse::json([]);
    }

    public function preflight(): ApiResponse
    {
        return ApiResponse::noContent();
    }

    // --- Authorization (browser) -----------------------------------------------

    /**
     * GET /oauth/authorize: validates the request before the login, so a
     * broken request does not send the user through the login first.
     *
     * @param callable(Request): void $guardedConsent consent() wrapped by AuthGuard::guard(null, …)
     */
    public function authorize(Request $request, callable $guardedConsent): void
    {
        if (!$this->available()) {
            Response::notFound();

            return;
        }
        try {
            $this->oauth->validateAuthorizationRequest($request->query, self::resource($_SERVER));
        } catch (OAuthError $e) {
            $this->fail($e);

            return;
        }
        $guardedConsent($request);
    }

    /** Consent page (after login). */
    public function consent(Request $request): void
    {
        $user = CurrentUser::get();
        try {
            $authorization = $this->oauth->validateAuthorizationRequest($request->query, self::resource($_SERVER));
        } catch (OAuthError $e) {
            $this->fail($e);

            return;
        }
        if ($user === null || !OAuthScopePolicy::mayConnect($user)) {
            $this->fail(new OAuthError('access_denied', t('oauth.error.no_permissions'), 403, $authorization->redirectUri, $authorization->state));

            return;
        }
        $this->renderConsent($authorization, $this->oauth->offeredScopes($authorization, $user), []);
    }

    /** POST /oauth/authorize: the user allowed or denied access. */
    public function decide(Request $request): void
    {
        $user = CurrentUser::get();
        try {
            $authorization = $this->oauth->validateAuthorizationRequest($request->post, self::resource($_SERVER));
        } catch (OAuthError $e) {
            $this->fail($e);

            return;
        }
        if ($user === null || $request->input('decision') !== 'allow') {
            Response::redirect($this->oauth->deny($authorization, self::issuer($_SERVER)));

            return;
        }
        try {
            Response::redirect($this->oauth->approve($authorization, $user, $request->inputArray('scopes'), self::issuer($_SERVER)));
        } catch (OAuthError $e) {
            $this->renderConsent($authorization, $this->oauth->offeredScopes($authorization, $user), ['scopes' => $e->description]);
        }
    }

    // --- Connected assistants -------------------------------------------------

    /** Profile › KI-Assistenten: the user's own connections. */
    public function profile(Request $request): void
    {
        $user = CurrentUser::get();
        echo View::page('profile/ai-assistants', [
            'connections' => $user !== null ? $this->oauth->connections($user->id) : [],
            'mcpAvailable' => $this->available(),
            'mayConnect' => $user !== null && OAuthScopePolicy::mayConnect($user),
            'mcpUrl' => self::resource($_SERVER),
        ], title: t('profile.ai.title'));
    }

    public function disconnectOwn(Request $request): void
    {
        $user = CurrentUser::get();
        if ($user !== null && $this->oauth->disconnect($request->param('id'), $user->id)) {
            Flash::success(t('profile.ai.disconnected'));
        } else {
            Flash::error(t('profile.ai.not_found'));
        }
        Response::redirect('/profil/ki-assistenten');
    }

    /** Settings › API: administrators disconnect any assistant. */
    public function disconnectAny(Request $request): void
    {
        if ($this->oauth->disconnect($request->param('id'), null)) {
            Flash::success('Die Verbindung wurde getrennt.');
        } else {
            Flash::error('Die Verbindung wurde nicht gefunden.');
        }
        Response::redirect('/einstellungen/api');
    }

    // --- Helpers ---------------------------------------------------------------

    /**
     * @param list<ApiScope> $offered
     * @param array<string, string> $errors
     */
    private function renderConsent(AuthorizationRequest $authorization, array $offered, array $errors): void
    {
        $requested = $authorization->requestedScopes;
        echo View::page('oauth/consent', [
            'authorization' => $authorization,
            'clientName' => $authorization->client->name,
            'redirectHost' => $authorization->client->redirectHost($authorization->redirectUri),
            'offeredScopes' => $offered,
            'preselected' => $requested ?? $offered,
            'mcpReadOnly' => $this->settings->get()->mcpReadOnly,
            'user' => CurrentUser::get(),
            'errors' => $errors,
        ], title: t('oauth.consent.title'));
    }

    /** Errors with a verified redirect URI go back to the client, all others are shown to the user. */
    private function fail(OAuthError $error): void
    {
        $redirect = $error->redirectUrl(self::issuer($_SERVER));
        if ($redirect !== null) {
            Response::redirect($redirect);

            return;
        }
        http_response_code($error->status >= 400 && $error->status < 600 ? $error->status : 400);
        echo View::page('oauth/error', ['error' => $error], title: t('oauth.error.title'));
    }

    private function available(): bool
    {
        return $this->settings->get()->mcpAvailable();
    }

    /** @param array<string, mixed> $server */
    private function limit(string $endpoint, array $server, int $perMinute): ?ApiResponse
    {
        $key = 'oauth-' . substr(hash('sha256', $endpoint . '|' . ApiKernel::clientIp($server)), 0, 30);
        $result = $this->kernel->rateLimitKey($key, $perMinute, $this->settings->get(), $this->now());

        return $result instanceof ApiResponse
            ? ApiResponse::json(['error' => 'slow_down', 'error_description' => 'Too many requests, please retry later.'], 429)->withHeaders($result->headers)
            : null;
    }

    /**
     * Client credentials from "Authorization: Basic" (RFC 6749 section 2.3.1:
     * id and secret are form-urlencoded before base64).
     *
     * @param array<string, mixed> $server
     * @return array{0: string, 1: string}|null
     */
    public static function basicAuth(array $server): ?array
    {
        $header = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if (is_string($header) && preg_match('/^\s*Basic\s+([A-Za-z0-9+\/=]+)\s*$/i', $header, $m) === 1) {
            $decoded = base64_decode($m[1], true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$id, $secret] = explode(':', $decoded, 2);

                return [urldecode($id), urldecode($secret)];
            }
        }
        if (is_string($server['PHP_AUTH_USER'] ?? null)) {
            return [$server['PHP_AUTH_USER'], is_string($server['PHP_AUTH_PW'] ?? null) ? $server['PHP_AUTH_PW'] : ''];
        }

        return null;
    }

    /** @return list<string> */
    private static function supportedScopes(): array
    {
        return array_values(array_map(
            static fn (ApiScope $s) => $s->value,
            array_filter(ApiScope::cases(), static fn (ApiScope $s) => !in_array($s, [ApiScope::InvoicesFinalize, ApiScope::EmailSend], true)),
        ));
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
