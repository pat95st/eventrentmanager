<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mcp;

use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ApiSettings;
use App\Infrastructure\Http\Api\ApiKernel;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\RateLimit\RateLimiterInterface;
use App\Infrastructure\Http\Api\RateLimit\RateLimitResult;
use App\Infrastructure\Mcp\McpServer;
use App\Infrastructure\Mcp\McpToolbox;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryApiRequestLogRepository;
use Tests\Support\Fakes\InMemoryApiSettingsRepository;
use Tests\Support\Fakes\InMemoryApiTokenRepository;

final class McpServerTest extends Unit
{
    private InMemoryApiTokenRepository $tokens;
    private InMemoryApiRequestLogRepository $log;
    private InMemoryApiSettingsRepository $settings;
    /** @var list<ApiRequest> */
    private array $calls = [];
    private ?RateLimiterInterface $limiter = null;

    protected function _before(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->log = new InMemoryApiRequestLogRepository();
        $this->settings = new InMemoryApiSettingsRepository(new ApiSettings(enabled: true, rateLimitPerMinute: 0, mcpEnabled: true));
        $this->calls = [];
        $this->limiter = null;
    }

    public function testAnswers404UnlessApiAndMcpAreEnabled(): void
    {
        $this->addToken([ApiScope::CustomersRead]);
        $this->settings->save(new ApiSettings(enabled: true, mcpEnabled: false));
        $this->assertSame(404, $this->rpc('ping')->status);
        $this->settings->save(new ApiSettings(enabled: false, mcpEnabled: true));
        $this->assertSame(404, $this->rpc('ping')->status);
    }

    public function testOnlyPostIsSupported(): void
    {
        $this->addToken([ApiScope::CustomersRead]);
        $response = $this->server()->handle('GET', $this->serverVars('secret'));

        $this->assertSame(405, $response->status);
        $this->assertSame('POST', $response->headers['Allow']);
    }

    public function testRequiresAValidTokenReleasedForMcp(): void
    {
        $this->addToken([ApiScope::CustomersRead], mcpAllowed: false);

        $this->assertSame(401, $this->rpc('ping', token: null)->status);
        $this->assertSame(401, $this->rpc('ping', token: 'wrong')->status);
        $this->assertSame(403, $this->rpc('ping')->status);
    }

    public function testUnauthorizedResponseCarriesConfiguredChallenge(): void
    {
        $server = new McpServer($this->settings, $this->kernel(), new McpToolbox([]), static fn () => 'Bearer resource_metadata="https://verleih.test/.well-known/oauth-protected-resource"');
        $response = $server->handle('POST', ['REMOTE_ADDR' => '192.0.2.1'], '{}');

        $this->assertSame(401, $response->status);
        $this->assertStringContainsString('resource_metadata=', $response->headers['WWW-Authenticate']);
    }

    public function testRejectsUnsupportedProtocolVersionHeader(): void
    {
        $this->addToken([ApiScope::CustomersRead]);

        $this->assertSame(400, $this->rpc('ping', extraServer: ['HTTP_MCP_PROTOCOL_VERSION' => '1999-01-01'])->status);
        $this->assertSame(200, $this->rpc('ping', extraServer: ['HTTP_MCP_PROTOCOL_VERSION' => '2025-06-18'])->status);
    }

    public function testInitializeNegotiatesTheProtocolVersion(): void
    {
        $this->addToken([ApiScope::CustomersRead]);

        $result = $this->rpc('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']])->body()['result'];
        $this->assertSame('2025-03-26', $result['protocolVersion']);
        $this->assertSame(['listChanged' => false], $result['capabilities']['tools']);
        $this->assertSame('materialverleih', $result['serverInfo']['name']);
        $this->assertNotEmpty($result['instructions']);

        $result = $this->rpc('initialize', ['protocolVersion' => '2030-01-01'])->body()['result'];
        $this->assertSame(McpServer::PROTOCOL_VERSIONS[0], $result['protocolVersion']);
    }

    public function testNotificationsAndClientResponsesAreAccepted(): void
    {
        $this->addToken([ApiScope::CustomersRead]);

        $response = $this->server()->handle('POST', $this->serverVars('secret'), '{"jsonrpc":"2.0","method":"notifications/initialized"}');
        $this->assertSame(202, $response->status);
        $this->assertNull($response->json);

        $response = $this->server()->handle('POST', $this->serverVars('secret'), '{"jsonrpc":"2.0","id":7,"result":{}}');
        $this->assertSame(202, $response->status);
    }

    public function testMalformedMessagesAreJsonRpcErrors(): void
    {
        $this->addToken([ApiScope::CustomersRead]);

        $parse = $this->server()->handle('POST', $this->serverVars('secret'), '{not json');
        $this->assertSame(400, $parse->status);
        $this->assertSame(-32700, $parse->body()['error']['code']);

        $batch = $this->server()->handle('POST', $this->serverVars('secret'), '[{"jsonrpc":"2.0","id":1,"method":"ping"}]');
        $this->assertSame(-32600, $batch->body()['error']['code']);

        $this->assertSame(-32601, $this->rpc('resources/list')->body()['error']['code']);
        $this->assertSame(-32602, $this->rpc('tools/call', ['name' => 'does_not_exist'])->body()['error']['code']);
    }

    public function testToolListFollowsScopesAndReadOnlySwitches(): void
    {
        $this->addToken([ApiScope::CustomersRead, ApiScope::CustomersWrite]);
        $names = $this->toolNames();
        $this->assertSame(['search_customers', 'get_customer', 'create_customer', 'update_customer'], $names);

        // Token flagged read only: write tools disappear.
        $this->addToken([ApiScope::CustomersRead, ApiScope::CustomersWrite], readOnly: true);
        $this->assertSame(['search_customers', 'get_customer'], $this->toolNames());

        // Global MCP read-only switch: same effect for every token.
        $this->addToken([ApiScope::CustomersRead, ApiScope::CustomersWrite]);
        $this->settings->save(new ApiSettings(enabled: true, rateLimitPerMinute: 0, mcpEnabled: true, mcpReadOnly: true));
        $this->assertSame(['search_customers', 'get_customer'], $this->toolNames());
    }

    public function testToolDefinitionsCarryAnnotationsAndSchemas(): void
    {
        $this->addToken([ApiScope::CustomersRead, ApiScope::CustomersWrite]);
        $tools = $this->rpc('tools/list')->body()['result']['tools'];
        $byName = array_column($tools, null, 'name');

        $this->assertTrue($byName['search_customers']['annotations']['readOnlyHint']);
        $this->assertFalse($byName['create_customer']['annotations']['readOnlyHint']);
        $this->assertFalse($byName['create_customer']['annotations']['destructiveHint']);
        $this->assertSame('object', $byName['create_customer']['inputSchema']['type']);
        $this->assertContains('last_name', $byName['create_customer']['inputSchema']['required']);
        $this->assertSame(['customer_id'], $byName['update_customer']['inputSchema']['required']);
    }

    public function testToolCallReturnsCompactStructuredResultWithLinksAndIsLogged(): void
    {
        $this->addToken([ApiScope::CustomersRead]);

        $response = $this->rpc('tools/call', ['name' => 'search_customers', 'arguments' => ['query' => 'Müller', 'limit' => 1]]);
        $result = $response->body()['result'];

        $this->assertFalse($result['isError']);
        $this->assertSame(['page' => '1', 'page_size' => '1', 'q' => 'Müller', 'sort' => 'display_name'], $this->sortedQuery($this->calls[0]));
        $item = $result['structuredContent']['items'][0];
        $this->assertSame('c1', $item['id']);
        $this->assertSame('Anna Müller', $item['display_name']);
        $this->assertArrayNotHasKey('street', $item, 'list results are reduced to the essential fields');
        $this->assertArrayNotHasKey('email', $item, 'empty fields are dropped');
        $this->assertSame('https://verleih.test/kunden/c1/bearbeiten', $item['url']);
        $this->assertTrue($result['structuredContent']['has_more']);
        $this->assertSame($result['structuredContent'], json_decode($result['content'][0]['text'], true));

        $this->assertCount(1, $this->log->entries);
        $entry = $this->log->entries[0];
        $this->assertSame('MCP', $entry->method);
        $this->assertSame(200, $entry->statusCode);
        $this->assertStringContainsString('search_customers {"query":"Müller","limit":1} → 1 result', $entry->path);
    }

    public function testWriteToolIsRefusedWhileMcpIsReadOnly(): void
    {
        $this->addToken([ApiScope::CustomersRead, ApiScope::CustomersWrite]);
        $this->settings->save(new ApiSettings(enabled: true, rateLimitPerMinute: 0, mcpEnabled: true, mcpReadOnly: true));

        $result = $this->rpc('tools/call', ['name' => 'create_customer', 'arguments' => ['type' => 'privat']])->body()['result'];

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('read only', $result['content'][0]['text']);
        $this->assertSame([], $this->calls);
        $this->assertSame(403, $this->log->entries[0]->statusCode);
    }

    public function testToolWithoutScopeIsRefused(): void
    {
        $this->addToken([ApiScope::CustomersRead]);

        $result = $this->rpc('tools/call', ['name' => 'list_invoices', 'arguments' => []])->body()['result'];

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('invoices:read', $result['content'][0]['text']);
    }

    public function testMissingRequiredArgumentsAndValidationErrorsAreToolErrors(): void
    {
        $this->addToken([ApiScope::CustomersRead, ApiScope::CustomersWrite]);

        $missing = $this->rpc('tools/call', ['name' => 'get_customer', 'arguments' => []])->body()['result'];
        $this->assertTrue($missing['isError']);
        $this->assertStringContainsString('customer_id', $missing['content'][0]['text']);

        $invalid = $this->rpc('tools/call', ['name' => 'create_customer', 'arguments' => ['type' => 'privat', 'first_name' => 'A', 'last_name' => 'B', 'street' => 'S', 'postal_code' => 'x', 'city' => 'C']])->body()['result'];
        $this->assertTrue($invalid['isError']);
        $this->assertStringContainsString('postal_code: Ungültige Postleitzahl.', $invalid['content'][0]['text']);
        $this->assertSame(422, $this->log->entries[1]->statusCode);
    }

    public function testNotFoundFromTheApiIsAToolError(): void
    {
        $this->addToken([ApiScope::CustomersRead]);

        $result = $this->rpc('tools/call', ['name' => 'get_customer', 'arguments' => ['customer_id' => 'missing']])->body()['result'];

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('nicht gefunden', $result['content'][0]['text']);
    }

    public function testRateLimitIsSharedWithTheApi(): void
    {
        $this->addToken([ApiScope::CustomersRead], rateLimit: 1);

        $this->assertSame(200, $this->rpc('ping')->status);
        $response = $this->rpc('ping');
        $this->assertSame(429, $response->status);
        $this->assertArrayHasKey('Retry-After', $response->headers);
    }

    // --- helpers -------------------------------------------------------------

    /** @param list<ApiScope> $scopes */
    private function addToken(array $scopes, bool $readOnly = false, bool $mcpAllowed = true, ?int $rateLimit = null): void
    {
        $this->tokens->save(new ApiToken('t1', 'Claude', hash('sha256', 'secret'), 'cret', null, true, null, new DateTimeImmutable('2026-01-01'), $scopes, $readOnly, $rateLimit, $mcpAllowed));
    }

    /** @return list<string> */
    private function toolNames(): array
    {
        return array_column($this->rpc('tools/list')->body()['result']['tools'], 'name');
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $extraServer
     */
    private function rpc(string $method, array $params = [], ?string $token = 'secret', array $extraServer = []): ApiResponse
    {
        $body = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params === [] ? new \stdClass() : $params], JSON_THROW_ON_ERROR);

        return $this->server()->handle('POST', $this->serverVars($token) + $extraServer, $body);
    }

    /** @return array<string, string> */
    private function serverVars(?string $token): array
    {
        $server = ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_HOST' => 'verleih.test', 'HTTPS' => 'on'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        return $server;
    }

    private function server(): McpServer
    {
        return new McpServer($this->settings, $this->kernel(), new McpToolbox(['customers' => $this->customerController()]));
    }

    private function kernel(): ApiKernel
    {
        // One limiter per test, so consecutive requests share the counter.
        $this->limiter ??= new class () implements RateLimiterInterface {
            private int $hits = 0;

            public function hit(string $tokenId, int $limit, DateTimeImmutable $now): RateLimitResult
            {
                $this->hits++;

                return new RateLimitResult($limit, $this->hits, RateLimitResult::window($now)[1]);
            }
        };
        $limiter = $this->limiter;

        return new ApiKernel($this->settings, $this->tokens, $this->log, static fn () => $limiter, static fn () => new DateTimeImmutable('2026-11-17 10:00:30'));
    }

    /** Stands in for CustomerApiController: records the requests it receives. */
    private function customerController(): object
    {
        $calls = &$this->calls;

        return new class ($calls) {
            /** @param list<ApiRequest> $calls */
            public function __construct(private array &$calls)
            {
            }

            public function index(ApiRequest $request): ApiResponse
            {
                $this->calls[] = $request;

                return ApiResponse::json([
                    'data' => [['id' => 'c1', 'customer_number' => 'K-1', 'display_name' => 'Anna Müller', 'type' => 'privat', 'email' => null, 'street' => 'Weg 1']],
                    'meta' => ['page' => 1, 'page_size' => 1, 'total' => 3, 'total_pages' => 3],
                ]);
            }

            public function show(ApiRequest $request): ApiResponse
            {
                $this->calls[] = $request;
                if ($request->param('id') === 'missing') {
                    throw new \App\Domain\Exception\NotFoundException('Kunde wurde nicht gefunden.');
                }

                return ApiResponse::data(['id' => $request->param('id'), 'display_name' => 'Anna Müller']);
            }

            public function store(ApiRequest $request): ApiResponse
            {
                $this->calls[] = $request;

                throw new ValidationException(['postalCode' => 'Ungültige Postleitzahl.']);
            }
        };
    }

    /** @return array<string, mixed> */
    private function sortedQuery(ApiRequest $request): array
    {
        $query = $request->query;
        ksort($query);

        return $query;
    }
}
