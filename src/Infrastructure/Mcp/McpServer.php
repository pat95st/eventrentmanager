<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Domain\Api\ApiToken;
use App\Domain\Settings\ApiSettings;
use App\Domain\Settings\ApiSettingsRepositoryInterface;
use App\Infrastructure\Http\Api\ApiEmitter;
use App\Infrastructure\Http\Api\ApiKernel;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Request;
use Closure;
use DateTimeImmutable;
use JsonException;
use stdClass;
use Throwable;

/**
 * MCP server (Model Context Protocol) at /mcp, "Streamable HTTP" transport
 * without server-sent events: every JSON-RPC request is answered with a
 * single JSON response, notifications with 202. No session state is kept
 * (no Mcp-Session-Id), so it runs on plain PHP hosting.
 *
 * Request pipeline:
 *  1. REST API and MCP switched on (ApiSettings::mcpAvailable(); else 404),
 *  2. POST only (GET/DELETE → 405: no SSE stream, no sessions),
 *  3. bearer token, IP filter (shared with ApiKernel; 401/403),
 *  4. token released for MCP (ApiToken::$mcpAllowed; 403),
 *  5. MCP-Protocol-Version header, if sent, is supported (400),
 *  6. rate limit per token (shared counter with the REST API; 429),
 *  7. JSON-RPC: initialize, ping, tools/list, tools/call.
 *
 * Tools are filtered per token: only tools whose scopes the token holds are
 * listed, write tools only while neither the token nor the global MCP
 * setting is read only. Every tools/call is written to api_request_log
 * (method "MCP", tool, arguments and number of results — not the result).
 *
 * Authentication uses the bearer token only, never cookies, so cross-site
 * requests from a browser (DNS rebinding) cannot act with a user's session.
 */
final class McpServer
{
    public const PATH = '/mcp';
    /** Newest first; the first one is offered when the client asks for an unknown version. */
    public const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];
    public const SERVER_NAME = 'materialverleih';
    public const SERVER_VERSION = '1.0.0';
    private const LOG_METHOD = 'MCP';

    // JSON-RPC 2.0 error codes
    private const PARSE_ERROR = -32700;
    private const INVALID_REQUEST = -32600;
    private const METHOD_NOT_FOUND = -32601;
    private const INVALID_PARAMS = -32602;
    private const INTERNAL_ERROR = -32603;

    /**
     * @param (Closure(array<string, mixed>): string)|null $wwwAuthenticate value of WWW-Authenticate on 401 (OAuth resource metadata)
     * @param (Closure(): DateTimeImmutable)|null $clock
     */
    public function __construct(
        private readonly ApiSettingsRepositoryInterface $settings,
        private readonly ApiKernel $kernel,
        private readonly McpToolbox $toolbox,
        private readonly ?Closure $wwwAuthenticate = null,
        private readonly ?Closure $clock = null,
    ) {
    }

    /** Router handler for every method on /mcp. */
    public function route(): callable
    {
        return function (Request $request): void {
            ApiEmitter::emit($this->handle($request->method, $_SERVER));
        };
    }

    /**
     * Processes one HTTP request; separated from route() for tests.
     *
     * @param array<string, mixed> $server $_SERVER-like array
     */
    public function handle(string $method, array $server, ?string $rawBody = null): ApiResponse
    {
        $now = $this->now();
        $settings = $this->settings->get();
        if (!$settings->mcpAvailable()) {
            return ApiResponse::error(404, 'Nicht gefunden.');
        }
        if ($method !== 'POST') {
            return ApiResponse::error(405, 'Dieser MCP-Server unterstützt nur POST (kein SSE-Stream, keine Sitzungen).')
                ->withHeaders(['Allow' => 'POST']);
        }

        $token = $this->kernel->authenticate($server, $this->wwwAuthenticate !== null ? ($this->wwwAuthenticate)($server) : 'Bearer');
        if ($token instanceof ApiResponse) {
            return $token;
        }
        if (!$token->mcpAllowed) {
            return ApiResponse::error(403, 'Dieses Token ist nicht für MCP freigegeben (Einstellungen › API › Token bearbeiten › „Für MCP zulassen“).');
        }

        $version = $server['HTTP_MCP_PROTOCOL_VERSION'] ?? null;
        if (is_string($version) && $version !== '' && !in_array($version, self::PROTOCOL_VERSIONS, true)) {
            return ApiResponse::error(400, 'Nicht unterstützte MCP-Protokollversion "' . $version . '". Unterstützt: ' . implode(', ', self::PROTOCOL_VERSIONS) . '.');
        }

        $rateHeaders = $this->kernel->rateLimit($token, $settings, $now);
        if ($rateHeaders instanceof ApiResponse) {
            return $rateHeaders;
        }
        $this->kernel->touchLastUsed($token, $now);

        return $this->dispatch($rawBody ?? (string) file_get_contents('php://input'), $token, $settings, $server, $now)
            ->withHeaders($rateHeaders);
    }

    /** @param array<string, mixed> $server */
    private function dispatch(string $raw, ApiToken $token, ApiSettings $settings, array $server, DateTimeImmutable $now): ApiResponse
    {
        try {
            $message = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::rpcError(null, self::PARSE_ERROR, 'Parse error: the body is not valid JSON.');
        }
        if (!is_array($message) || array_is_list($message)) {
            // JSON-RPC batches were removed from MCP in protocol version 2025-06-18.
            return self::rpcError(null, self::INVALID_REQUEST, 'Invalid request: expected a single JSON-RPC message object.');
        }

        $id = $message['id'] ?? null;
        $hasId = array_key_exists('id', $message) && (is_string($id) || is_int($id));
        $rpcMethod = $message['method'] ?? null;
        if (!is_string($rpcMethod)) {
            // A response or error from the client (we never send requests): acknowledge.
            return ($message['jsonrpc'] ?? null) === '2.0' && (isset($message['result']) || isset($message['error']))
                ? ApiResponse::accepted()
                : self::rpcError($hasId ? $id : null, self::INVALID_REQUEST, 'Invalid request: "method" is missing.');
        }
        if (!$hasId) {
            return ApiResponse::accepted(); // notification, e.g. notifications/initialized
        }
        $params = $message['params'] ?? [];
        if (!is_array($params)) {
            return self::rpcError($id, self::INVALID_PARAMS, 'Invalid params: "params" must be an object.');
        }

        return match ($rpcMethod) {
            'initialize' => self::rpcResult($id, $this->initialize($params)),
            'ping' => self::rpcResult($id, new stdClass()),
            'tools/list' => self::rpcResult($id, ['tools' => $this->listTools($token, $settings)]),
            'tools/call' => $this->callTool($id, $params, $token, $settings, $server, $now),
            default => self::rpcError($id, self::METHOD_NOT_FOUND, 'Method not found: ' . $rpcMethod),
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;

        return [
            'protocolVersion' => is_string($requested) && in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => self::SERVER_NAME, 'title' => 'Materialverleih', 'version' => self::SERVER_VERSION],
            'instructions' => self::instructions(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function listTools(ApiToken $token, ApiSettings $settings): array
    {
        $tools = [];
        foreach ($this->toolbox->tools() as $tool) {
            if ($tool->isAvailableFor($token, $settings->mcpReadOnly)) {
                $tools[] = $tool->definition();
            }
        }

        return $tools;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $server
     */
    private function callTool(int|string $id, array $params, ApiToken $token, ApiSettings $settings, array $server, DateTimeImmutable $now): ApiResponse
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];
        if (!is_string($name) || $name === '') {
            return self::rpcError($id, self::INVALID_PARAMS, 'Invalid params: "name" of the tool is missing.');
        }
        $tool = $this->toolbox->find($name);
        if ($tool === null) {
            return self::rpcError($id, self::INVALID_PARAMS, 'Unknown tool: ' . $name);
        }
        if (!is_array($arguments) || ($arguments !== [] && array_is_list($arguments))) {
            return self::rpcError($id, self::INVALID_PARAMS, 'Invalid params: "arguments" must be an object.');
        }

        $ip = ApiKernel::clientIp($server);
        $logArguments = self::shorten(json_encode($arguments === [] ? new stdClass() : $arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', 300);
        $log = fn (int $status, string $outcome) => $this->kernel->log($token, self::LOG_METHOD, $name . ' ' . $logArguments . ' → ' . $outcome, $status, $ip !== '' ? $ip : null, $now);

        if (!$tool->isAvailableFor($token, $settings->mcpReadOnly)) {
            $log(403, 'denied');

            return self::rpcResult($id, self::toolError(
                $tool->readOnly || !($settings->mcpReadOnly || $token->readOnly)
                    ? 'This token lacks the permission for "' . $name . '" (scopes: ' . implode(', ', array_map(static fn ($s) => $s->value, $tool->scopes)) . ').'
                    : 'MCP access is read only: "' . $name . '" changes data and is not available.'
            ));
        }

        $missing = self::missingRequired($tool->inputSchema, $arguments);
        if ($missing !== []) {
            $log(422, 'missing ' . implode(', ', $missing));

            return self::rpcResult($id, self::toolError('Missing required argument(s): ' . implode(', ', $missing) . '.'));
        }

        try {
            $result = $tool->call($arguments, new McpToolContext($token, self::origin($server)));
        } catch (McpToolError $e) {
            $log($e->logStatus, 'error: ' . self::shorten($e->getMessage(), 120));

            return self::rpcResult($id, self::toolError($e->getMessage(), $e->fieldErrors));
        } catch (Throwable $e) {
            error_log('MCP tool ' . $name . ' failed: ' . $e);
            $log(500, 'internal error');

            return self::rpcResult($id, self::toolError('Internal error while running the tool. Please try again or use the web interface.'));
        }

        $count = isset($result['items']) && is_array($result['items']) ? count($result['items']) : 1;
        $log(200, $count . ' ' . ($count === 1 ? 'result' : 'results'));

        $structured = $result === [] ? new stdClass() : $result;

        return self::rpcResult($id, [
            'content' => [['type' => 'text', 'text' => json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)]],
            'structuredContent' => $structured,
            'isError' => false,
        ]);
    }

    /**
     * Top-level "required" check of the arguments; detailed validation is
     * done by the commands behind the tools.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $arguments
     * @return list<string>
     */
    private static function missingRequired(array $schema, array $arguments): array
    {
        $missing = [];
        foreach ((array) ($schema['required'] ?? []) as $field) {
            $value = $arguments[$field] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $missing[] = (string) $field;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, string> $fieldErrors
     * @return array<string, mixed>
     */
    private static function toolError(string $message, array $fieldErrors = []): array
    {
        $text = $message;
        foreach ($fieldErrors as $field => $error) {
            $text .= "\n- " . $field . ': ' . $error;
        }

        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => true];
    }

    private static function rpcResult(int|string $id, mixed $result): ApiResponse
    {
        return ApiResponse::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private static function rpcError(int|string|null $id, int $code, string $message): ApiResponse
    {
        // HTTP 200 with a JSON-RPC error object, except for unparseable messages.
        return ApiResponse::json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]], $id === null ? 400 : 200);
    }

    /** Scheme + host of the request, for links into the web interface (no hard-coded domain). */
    public static function origin(array $server): string
    {
        $host = is_string($server['HTTP_HOST'] ?? null) && $server['HTTP_HOST'] !== '' ? $server['HTTP_HOST'] : 'localhost';
        $https = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off')
            || ($server['SERVER_PORT'] ?? null) === '443'
            || ($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        return ($https ? 'https://' : 'http://') . $host;
    }

    private static function shorten(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }

    public static function instructions(): string
    {
        return <<<'TXT'
            Materialverleih is the management software of an event technology rental company (sound, light, stage, power).
            Data and the web interface are German; tool descriptions give the German terms in parentheses — use them to map the user's wording:
            customer = Kunde, article = Artikel (equipment type), inventory number = Inventurnummer (single unit), event/rental = Veranstaltung,
            offer = Angebot, order confirmation = Auftragsbestätigung, invoice = Rechnung, dunning notice = Mahnung, receipt = Beleg, availability = Verfügbarkeit.

            Conventions: dates YYYY-MM-DD, times ISO 8601, amounts in EUR as decimal strings with a decimal point, rental prices are per day.
            List tools return {items, page, limit, total, has_more}; ask for the next page when has_more is true instead of guessing.
            Look up ids with the search/list tools instead of inventing them. Before planning material for an event or offer, check availability.
            Results contain a "url" to the record in the web interface — share it so the user can open, print or send the document there.

            Not possible via MCP (only in the web interface): finalizing or cancelling invoices, sending e-mails to customers, deleting records,
            changing articles or master data, issuing/returning material. Which tools are offered depends on the permissions of the access token.
            Write tools change real business data: confirm with the user before creating or changing records unless they asked for it explicitly.
            TXT;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
