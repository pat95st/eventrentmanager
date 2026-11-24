<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Domain\Api\ApiRequestLogEntry;
use App\Domain\Api\ApiRequestLogRepositoryInterface;
use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use App\Domain\Api\ApiTokenRepositoryInterface;
use App\Domain\Api\IpAllowlist;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ApiSettings;
use App\Domain\Settings\ApiSettingsRepositoryInterface;
use App\Infrastructure\Http\Api\RateLimit\RateLimiterInterface;
use App\Infrastructure\Http\Api\RateLimit\RateLimiterUnavailable;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Mail\MailerException;
use Closure;
use DateTimeImmutable;
use JsonException;
use Throwable;

/**
 * Front controller of the REST API /api/v1. Every route handler is wrapped
 * with route(), which performs — before the controller runs — in this order:
 *
 * 1. global switch (ApiSettings::$enabled; off = 404 for every route, so the
 *    API's existence is not revealed),
 * 2. "Authorization: Bearer <token>" present and known, token enabled (401),
 * 3. client IP in the token's allowlist (403),
 * 4. required scopes granted; read-only tokens never pass a write scope or a
 *    write method (403),
 * 5. rate limit per token and minute (429 with Retry-After),
 *
 * then decodes the body, calls the controller (which returns an
 * ApiResponse), maps domain exceptions to JSON errors and logs every
 * authenticated write request (api_request_log). No session or CSRF is
 * involved (see Router::CSRF_EXEMPT_PREFIXES).
 */
final class ApiKernel
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const PURGE_LOG_ONE_IN = 200;

    /**
     * @param Closure(ApiSettings): ?RateLimiterInterface $rateLimiterFactory returns null when rate limiting is off
     * @param (Closure(): DateTimeImmutable)|null $clock
     * @param (Closure(ApiToken, DateTimeImmutable): ?ApiToken)|null $tokenGuard checks/narrows a token after lookup
     *        (OAuth tokens: expiry, owner still active, owner's current permissions); null = reject
     */
    public function __construct(
        private readonly ApiSettingsRepositoryInterface $settings,
        private readonly ApiTokenRepositoryInterface $tokens,
        private readonly ApiRequestLogRepositoryInterface $requestLog,
        private readonly Closure $rateLimiterFactory,
        private readonly ?Closure $clock = null,
        private readonly ?Closure $tokenGuard = null,
    ) {
    }

    /**
     * Wraps a controller callable (ApiRequest => ApiResponse) as router handler.
     *
     * @param ApiScope|list<ApiScope> $scopes all of them are required
     */
    public function route(ApiScope|array $scopes, callable $handler): callable
    {
        $scopes = is_array($scopes) ? $scopes : [$scopes];

        return function (Request $request) use ($scopes, $handler): void {
            ApiEmitter::emit($this->handle($request, $scopes, $handler, $_SERVER));
        };
    }

    /**
     * Processes one request; separated from route() so it can be tested
     * without output/exit. $rawBody defaults to php://input.
     *
     * @param list<ApiScope> $scopes
     * @param array<string, mixed> $server $_SERVER-like array
     */
    public function handle(Request $request, array $scopes, callable $handler, array $server, ?string $rawBody = null): ApiResponse
    {
        $now = $this->now();
        $settings = $this->settings->get();
        if (!$settings->enabled) {
            return ApiResponse::error(404, 'Nicht gefunden.');
        }

        $token = $this->authenticate($server);
        if ($token instanceof ApiResponse) {
            return $token;
        }
        if ($token->isOAuth()) {
            // Issued for the resource /mcp (RFC 8707), not for the REST API.
            return ApiResponse::error(401, 'Über OAuth ausgestellte Tokens gelten nur für den MCP-Server (/mcp).')
                ->withHeaders(['WWW-Authenticate' => 'Bearer error="invalid_token"']);
        }
        $ip = self::clientIp($server);

        $isWrite = in_array($request->method, self::WRITE_METHODS, true);
        $response = $this->authorizeAndRun($request, $scopes, $handler, $token, $settings, $now, $isWrite, $rawBody);

        if ($isWrite) {
            $this->log($token, $request->method, $request->path, $response->status, $ip !== '' ? $ip : null, $now);
        }

        return $response;
    }

    /**
     * Steps 2 and 3 of the request pipeline, shared with the MCP server:
     * bearer token present, known and enabled (401), client IP allowed (403).
     *
     * @param array<string, mixed> $server $_SERVER-like array
     * @param string $wwwAuthenticate base value of the WWW-Authenticate header on 401
     */
    public function authenticate(array $server, string $wwwAuthenticate = 'Bearer'): ApiToken|ApiResponse
    {
        $rawToken = self::bearerToken($server);
        if ($rawToken === null) {
            return ApiResponse::error(401, 'Es wurde kein gültiger "Authorization: Bearer <token>"-Header mitgeschickt.')
                ->withHeaders(['WWW-Authenticate' => $wwwAuthenticate]);
        }
        $token = $this->tokens->findByTokenHash(hash('sha256', $rawToken));
        if ($token !== null && $this->tokenGuard !== null) {
            $token = ($this->tokenGuard)($token, $this->now());
        }
        if ($token === null || !$token->enabled) {
            return ApiResponse::error(401, 'Ungültiges oder deaktiviertes Token.')
                ->withHeaders(['WWW-Authenticate' => $wwwAuthenticate . ($wwwAuthenticate === 'Bearer' ? ' ' : ', ') . 'error="invalid_token"']);
        }

        if (!IpAllowlist::matches(self::clientIp($server), $token->ipAllowlist)) {
            return ApiResponse::error(403, 'Der Zugriff von dieser IP-Adresse ist für dieses Token nicht erlaubt.');
        }

        return $token;
    }

    /**
     * Rate limit per token and minute (step 5), shared with the MCP server.
     * Returns the X-RateLimit-* headers to add to the response, or the 429
     * response when the limit is exhausted.
     *
     * @return array<string, string>|ApiResponse
     */
    public function rateLimit(ApiToken $token, ApiSettings $settings, DateTimeImmutable $now): array|ApiResponse
    {
        return $this->rateLimitKey($token->id, $token->rateLimitPerMinute ?? $settings->rateLimitPerMinute, $settings, $now);
    }

    /**
     * Rate limit for an arbitrary key (max. 36 characters, e.g. a hash of the
     * client IP for the OAuth endpoints) in the same counter as the tokens.
     *
     * @return array<string, string>|ApiResponse
     */
    public function rateLimitKey(string $key, int $limit, ApiSettings $settings, DateTimeImmutable $now): array|ApiResponse
    {
        if ($limit <= 0) {
            return [];
        }
        $limiter = ($this->rateLimiterFactory)($settings);
        if ($limiter === null) {
            return [];
        }
        try {
            $result = $limiter->hit($key, $limit, $now);
            if (!$result->allowed()) {
                return ApiResponse::error(429, 'Zu viele Anfragen. Bitte nach Ablauf der Minute erneut versuchen.')
                    ->withHeaders($result->headers() + ['Retry-After' => (string) $result->retryAfterSeconds($now)]);
            }

            return $result->headers();
        } catch (RateLimiterUnavailable $e) {
            // Even the fallback failed (database trouble): do not block the API for that.
            error_log('API rate limiter unavailable: ' . $e->getMessage());

            return [];
        }
    }

    public function touchLastUsed(ApiToken $token, DateTimeImmutable $now): void
    {
        $this->tokens->touchLastUsed($token->id, $now);
    }

    /** @param list<ApiScope> $scopes */
    private function authorizeAndRun(Request $request, array $scopes, callable $handler, ApiToken $token, ApiSettings $settings, DateTimeImmutable $now, bool $isWrite, ?string $rawBody): ApiResponse
    {
        if ($token->readOnly && $isWrite) {
            return ApiResponse::error(403, 'Dieses Token ist auf "Nur lesen" gestellt und darf keine Daten ändern.');
        }
        $missing = array_values(array_filter($scopes, static fn (ApiScope $s) => !$token->allows($s)));
        if ($missing !== []) {
            return ApiResponse::error(403, 'Dem Token fehlt die Berechtigung: ' . implode(', ', array_map(static fn (ApiScope $s) => $s->value, $missing)) . '.')
                ->withHeaders(['WWW-Authenticate' => 'Bearer error="insufficient_scope", scope="' . implode(' ', array_map(static fn (ApiScope $s) => $s->value, $missing)) . '"']);
        }

        $rateHeaders = $this->rateLimit($token, $settings, $now);
        if ($rateHeaders instanceof ApiResponse) {
            return $rateHeaders;
        }

        $this->touchLastUsed($token, $now);

        try {
            $apiRequest = new ApiRequest(
                method: $request->method,
                path: $request->path,
                routeParams: $request->routeParams,
                query: $request->query,
                body: $isWrite ? $this->decodeBody($request, $rawBody) : [],
                files: $request->files,
                token: $token,
            );
            $response = $handler($apiRequest);
            if (!$response instanceof ApiResponse) {
                throw new \LogicException('API-Controller muss eine ApiResponse liefern.');
            }
        } catch (ApiException $e) {
            $response = ApiResponse::error($e->status, $e->getMessage(), $e->errors);
        } catch (ValidationException $e) {
            $response = ApiResponse::error(422, 'Die Eingaben sind ungültig.', self::snakeKeys($e->errors()));
        } catch (NotFoundException $e) {
            $response = ApiResponse::error(404, $e->getMessage());
        } catch (ConflictException $e) {
            $response = ApiResponse::error(409, $e->getMessage());
        } catch (MailerException $e) {
            $response = ApiResponse::error(502, 'Der E-Mail-Versand ist fehlgeschlagen: ' . $e->getMessage());
        } catch (JsonException) {
            $response = ApiResponse::error(400, 'Der Anfrage-Body ist kein gültiges JSON.');
        } catch (Throwable $e) {
            error_log('API error on ' . $request->method . ' ' . $request->path . ': ' . $e);
            $response = ApiResponse::error(500, 'Interner Fehler.');
        }

        return $response->withHeaders($rateHeaders);
    }

    /** @return array<string, mixed> */
    private function decodeBody(Request $request, ?string $rawBody): array
    {
        if ($request->post !== []) {
            return $request->post; // multipart/form-data or urlencoded
        }
        $raw = $rawBody ?? (string) file_get_contents('php://input');
        if (trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data) && $data !== []) {
            throw new ApiException(400, 'Der Anfrage-Body muss ein JSON-Objekt sein.');
        }

        return $data;
    }

    /** Adds an entry to api_request_log (90 days); also used by the MCP server. */
    public function log(ApiToken $token, string $method, string $path, int $status, ?string $ip, DateTimeImmutable $now): void
    {
        try {
            $this->requestLog->add(new ApiRequestLogEntry($token->id, $token->name, $method, mb_substr($path, 0, 500), $status, $ip, $now));
            if (random_int(1, self::PURGE_LOG_ONE_IN) === 1) {
                $this->requestLog->purgeOlderThan($now->modify('-' . ApiRequestLogRepositoryInterface::RETENTION_DAYS . ' days'));
            }
        } catch (Throwable $e) {
            error_log('API request log failed: ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $server */
    public static function clientIp(array $server): string
    {
        return is_string($server['REMOTE_ADDR'] ?? null) ? $server['REMOTE_ADDR'] : '';
    }

    /** @param array<string, mixed> $server */
    public static function bearerToken(array $server): ?string
    {
        $header = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if (!is_string($header) || preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /**
     * @param array<string, string> $errors
     * @return array<string, string>
     */
    private static function snakeKeys(array $errors): array
    {
        $result = [];
        foreach ($errors as $key => $message) {
            $result[Normalizer::snake((string) $key)] = $message;
        }

        return $result;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
