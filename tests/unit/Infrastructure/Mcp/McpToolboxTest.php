<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mcp;

use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\ApiRoutes;
use App\Infrastructure\Mcp\McpToolbox;
use App\Infrastructure\Mcp\McpToolContext;
use App\Infrastructure\Mcp\McpToolError;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class McpToolboxTest extends Unit
{
    private const SOURCE = __DIR__ . '/../../../../src/Infrastructure/Mcp/McpToolbox.php';

    /** Controller keys of bootstrap.php ($apiControllers) => class. */
    private const CONTROLLERS = [
        'customers' => \App\Infrastructure\Http\Controllers\Api\CustomerApiController::class,
        'articles' => \App\Infrastructure\Http\Controllers\Api\ArticleApiController::class,
        'rentals' => \App\Infrastructure\Http\Controllers\Api\RentalApiController::class,
        'offers' => \App\Infrastructure\Http\Controllers\Api\OfferApiController::class,
        'order-confirmations' => \App\Infrastructure\Http\Controllers\Api\OrderConfirmationApiController::class,
        'invoices' => \App\Infrastructure\Http\Controllers\Api\InvoiceApiController::class,
        'finance' => \App\Infrastructure\Http\Controllers\Api\FinanceApiController::class,
        'inventory' => \App\Infrastructure\Http\Controllers\Api\InventoryApiController::class,
        'reports' => \App\Infrastructure\Http\Controllers\Api\ReportApiController::class,
    ];

    public function testEveryReferencedControllerActionExistsAndIsARestRoute(): void
    {
        $routes = [];
        foreach (ApiRoutes::definitions() as [, , $scopes, $key, $action]) {
            $routes[$key . '::' . $action] = $scopes;
        }
        $calls = $this->referencedActions();
        $this->assertNotEmpty($calls);
        foreach ($calls as [$key, $action]) {
            $this->assertArrayHasKey($key, self::CONTROLLERS, 'Unknown controller key "' . $key . '".');
            $this->assertTrue(method_exists(self::CONTROLLERS[$key], $action), self::CONTROLLERS[$key] . '::' . $action . ' does not exist.');
            $this->assertArrayHasKey($key . '::' . $action, $routes, $key . '::' . $action . ' is not a route of ApiRoutes.');
        }
    }

    public function testNoIrreversibleOrDeletingActionIsUsed(): void
    {
        $forbidden = ['destroy', 'deleteItem', 'deleteImage', 'destroyInventoryNumber', 'destroyReceipt', 'destroyPurchaseOrder', 'finalize', 'cancel', 'email', 'emailPackingList', 'emailDunningNotice', 'emailCorrection'];
        foreach ($this->referencedActions() as [$key, $action]) {
            $this->assertNotContains($action, $forbidden, $key . '::' . $action . ' must not be reachable via MCP.');
        }
    }

    public function testToolScopesCoverTheScopesOfTheUnderlyingRoutes(): void
    {
        // Every tool must require at least one scope, and read tools only read scopes.
        foreach ((new McpToolbox([]))->tools() as $tool) {
            $this->assertNotEmpty($tool->scopes, $tool->name . ' has no scope.');
            if ($tool->readOnly) {
                foreach ($tool->scopes as $scope) {
                    $this->assertTrue($scope->isRead(), $tool->name . ' is read only but requires ' . $scope->value . '.');
                }
            }
        }
    }

    public function testToolDefinitionsAreValidJsonSchemaObjects(): void
    {
        $tools = (new McpToolbox([]))->tools();
        $this->assertGreaterThanOrEqual(30, count($tools));
        foreach ($tools as $name => $tool) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{2,63}$/', $name);
            $this->assertNotSame('', $tool->description);
            $definition = $tool->definition();
            $this->assertSame('object', $definition['inputSchema']['type'], $name);
            $json = json_encode($definition, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('"nullable"', $json, $name . ': OpenAPI "nullable" must be converted.');
            $this->assertStringNotContainsString('$ref', $json, $name . ': references must be inlined.');
            foreach ((array) ($definition['inputSchema']['required'] ?? []) as $field) {
                $this->assertArrayHasKey($field, (array) $definition['inputSchema']['properties'], $name . ': required field "' . $field . '" is not a property.');
            }
        }
    }

    public function testEveryToolIsDocumentedWithItsScopes(): void
    {
        $docs = (string) file_get_contents(__DIR__ . '/../../../../docs/mcp.md');
        foreach ((new McpToolbox([]))->tools() as $name => $tool) {
            $this->assertMatchesRegularExpression('/^\| `' . preg_quote($name, '/') . '` \| ' . ($tool->readOnly ? 'read' : 'write') . ' \|/m', $docs, $name . ' is missing in the tool table of docs/mcp.md (or has the wrong type).');
            $row = (string) preg_replace('/^.*?(\| `' . preg_quote($name, '/') . '` \|[^\n]*).*$/s', '$1', $docs);
            foreach ($tool->scopes as $scope) {
                $this->assertStringContainsString('`' . $scope->value . '`', $row, $name . ': scope ' . $scope->value . ' is not documented.');
            }
        }
    }

    public function testCompactDropsEmptyValuesRecursively(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => false, 'c' => ['x' => '0'], 'list' => [['k' => 'v']]],
            McpToolbox::compact(['a' => 1, 'b' => false, 'n' => null, 'e' => '', 'c' => ['x' => '0', 'y' => null], 'd' => ['z' => []], 'list' => [['k' => 'v'], ['k' => null]]]),
        );
    }

    public function testPagingIsClamped(): void
    {
        $this->assertSame([25, 1], McpToolbox::paging([]));
        $this->assertSame([100, 3], McpToolbox::paging(['limit' => 1000, 'page' => '3']));
        $this->assertSame([1, 1], McpToolbox::paging(['limit' => 0, 'page' => -2]));
    }

    public function testOverdueInvoicesAreFilteredByDueDate(): void
    {
        $invoices = new class () {
            /** @var array<string, mixed> */
            public array $query = [];

            public function index(ApiRequest $request): ApiResponse
            {
                $this->query = $request->query;

                return ApiResponse::json(['data' => [
                    ['id' => 'i1', 'invoice_number' => 'R-1', 'status' => 'sent', 'due_date' => '2026-11-01'],
                    ['id' => 'i2', 'invoice_number' => 'R-2', 'status' => 'sent', 'due_date' => '2026-12-01'],
                    ['id' => 'i3', 'invoice_number' => 'R-3', 'status' => 'sent', 'due_date' => null],
                ], 'meta' => ['total' => 3, 'total_pages' => 1]]);
            }
        };
        $toolbox = new McpToolbox(['invoices' => $invoices], null, static fn () => new DateTimeImmutable('2026-11-24'));

        $result = $toolbox->find('list_invoices')->call(['status' => 'overdue'], $this->context([ApiScope::InvoicesRead]));

        $this->assertSame('sent', $invoices->query['status']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('R-1', $result['items'][0]['invoice_number']);
        $this->assertTrue($result['items'][0]['overdue']);
        $this->assertSame('https://verleih.test/rechnungen/i1', $result['items'][0]['url']);
    }

    public function testCreateOfferFillsMissingUnitPricesFromTheArticle(): void
    {
        $offers = new class () {
            /** @var array<string, mixed> */
            public array $body = [];

            public function store(ApiRequest $request): ApiResponse
            {
                $this->body = $request->body;

                return ApiResponse::data(['id' => 'o1', 'offer_number' => 'A-1', 'note' => ''], 201);
            }
        };
        $articles = new class () {
            public int $lookups = 0;

            public function show(ApiRequest $request): ApiResponse
            {
                $this->lookups++;

                return ApiResponse::data(['id' => $request->param('id'), 'daily_rental_price' => '12.50']);
            }
        };
        $toolbox = new McpToolbox(['offers' => $offers, 'articles' => $articles]);

        $result = $toolbox->find('create_offer')->call([
            'customer_id' => 'c1', 'start_date' => '2026-12-05', 'end_date' => '2026-12-06', 'event_location' => 'Halle', 'valid_until' => '2026-12-01',
            'items' => [['article_id' => 'a1', 'quantity' => 2], ['article_id' => 'a1', 'quantity' => 1, 'unit_price' => 10], ['article_id' => 'a1', 'quantity' => 4]],
        ], $this->context([ApiScope::SalesWrite, ApiScope::ArticlesRead]));

        $this->assertSame(['article_id' => 'a1', 'quantity' => '2', 'unit_price' => '12.50'], $offers->body['items'][0]);
        $this->assertSame('10', $offers->body['items'][1]['unit_price'], 'explicit prices are kept');
        $this->assertSame('12.50', $offers->body['items'][2]['unit_price']);
        $this->assertSame(1, $articles->lookups, 'each article is looked up once');
        $this->assertSame('', $offers->body['note']);
        $this->assertSame('https://verleih.test/angebote/o1', $result['url']);
    }

    public function testConvertOfferChecksTheScopesOfTheTarget(): void
    {
        $offers = new class () {
            public function createOrderConfirmation(ApiRequest $request): ApiResponse
            {
                return ApiResponse::data(['id' => 'oc1'], 201);
            }

            public function convertToRental(ApiRequest $request): ApiResponse
            {
                return ApiResponse::data(['id' => 'r1'], 201);
            }
        };
        $tool = (new McpToolbox(['offers' => $offers]))->find('convert_offer');
        $context = $this->context([ApiScope::SalesRead, ApiScope::SalesWrite]);

        $result = $tool->call(['offer_id' => 'o1', 'target' => 'order_confirmation'], $context);
        $this->assertSame('https://verleih.test/auftragsbestaetigungen/oc1', $result['url']);

        try {
            $tool->call(['offer_id' => 'o1', 'target' => 'rental'], $context);
            $this->fail('rentals:write must be required.');
        } catch (McpToolError $e) {
            $this->assertStringContainsString('rentals:write', $e->getMessage());
            $this->assertSame(403, $e->logStatus);
        }
    }

    public function testInventoryNumberCanBeLookedUpByItsNumber(): void
    {
        $articles = new class () {
            public function showInventoryNumber(ApiRequest $request): ApiResponse
            {
                return ApiResponse::data(['id' => $request->param('id'), 'number' => 'LS-0042', 'article' => ['id' => 'a9', 'name' => 'Box']]);
            }
        };
        $repository = $this->makeEmpty(\App\Domain\InventoryNumber\InventoryNumberRepositoryInterface::class, [
            'findByNumber' => fn (string $number) => $number === 'LS-0042' ? $this->inventoryNumber('in1') : null,
        ]);
        $tool = (new McpToolbox(['articles' => $articles], $repository))->find('get_inventory_number');

        $result = $tool->call(['number' => ' LS-0042 '], $this->context([ApiScope::ArticlesRead]));
        $this->assertSame('in1', $result['id']);
        $this->assertSame('https://verleih.test/artikel/a9', $result['url']);
        $this->assertArrayNotHasKey('electrical_inspections', $result, 'inspections need inventory:read');

        $this->expectException(McpToolError::class);
        $tool->call(['number' => 'nope'], $this->context([ApiScope::ArticlesRead]));
    }

    // --- helpers -------------------------------------------------------------

    /** @return list<array{string, string}> [controller key, action] referenced via $this->api() */
    private function referencedActions(): array
    {
        preg_match_all("/\\\$this->api\\(\\\$ctx, '([a-z-]+)', '([A-Za-z]+)'/", (string) file_get_contents(self::SOURCE), $matches, PREG_SET_ORDER);
        $actions = array_map(static fn (array $m) => [$m[1], $m[2]], $matches);
        // Actions chosen at runtime via match().
        foreach (['markSent', 'accept', 'decline', 'createOrderConfirmation', 'convertToRental', 'createInvoice', 'incomeExpense', 'revenueForecast', 'depreciation'] as $dynamic) {
            $actions[] = [in_array($dynamic, ['incomeExpense', 'revenueForecast', 'depreciation'], true) ? 'reports' : 'offers', $dynamic];
        }

        return $actions;
    }

    /** @param list<ApiScope> $scopes */
    private function context(array $scopes): McpToolContext
    {
        return new McpToolContext(new ApiToken('t1', 'Claude', str_repeat('0', 64), '0000', null, true, null, new DateTimeImmutable(), $scopes, false, null, true), 'https://verleih.test');
    }

    private function inventoryNumber(string $id): \App\Domain\InventoryNumber\InventoryNumber
    {
        $reflection = new \ReflectionClass(\App\Domain\InventoryNumber\InventoryNumber::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('id');
        $property->setValue($instance, $id);

        return $instance;
    }
}
