<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Domain\Api\ApiScope as S;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Infrastructure\Http\Api\ApiException;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\Normalizer;
use Closure;
use DateTimeImmutable;
use LogicException;
use RuntimeException;

/**
 * The tools of the MCP server. Each tool is a task-oriented wrapper around
 * one or more actions of the REST API controllers (/api/v1): the same
 * commands, queries, validations and business rules apply, and the required
 * scopes are the ones of the corresponding REST routes (ApiRoutes).
 *
 * Deliberately not offered: finalizing/cancelling invoices, sending e-mails,
 * deleting anything, changing articles/master data, writing receipts and
 * purchase orders. Results are compact (empty fields dropped, lists reduced
 * to the essential fields) and carry a "url" to the page in the web
 * interface instead of PDFs.
 */
final class McpToolbox
{
    public const DEFAULT_LIMIT = 25;
    public const MAX_LIMIT = 100;
    private const MAX_CALENDAR_DAYS_WITH_DETAILS = 31;
    private const MAX_RENTAL_ITEMS_PER_CALL = 50;

    /** @var array<string, McpTool>|null */
    private ?array $tools = null;

    /**
     * @param array<string, object> $controllers REST API controllers by key (as for ApiRoutes::register())
     * @param (Closure(): DateTimeImmutable)|null $clock
     */
    public function __construct(
        private readonly array $controllers,
        private readonly ?InventoryNumberRepositoryInterface $inventoryNumbers = null,
        private readonly ?Closure $clock = null,
    ) {
    }

    /** @return array<string, McpTool> by name */
    public function tools(): array
    {
        if ($this->tools === null) {
            $this->tools = [];
            foreach ($this->definitions() as $tool) {
                $this->tools[$tool->name] = $tool;
            }
        }

        return $this->tools;
    }

    public function find(string $name): ?McpTool
    {
        return $this->tools()[$name] ?? null;
    }

    /** @return list<McpTool> */
    private function definitions(): array
    {
        return array_merge(
            $this->customerTools(),
            $this->articleTools(),
            $this->rentalTools(),
            $this->salesTools(),
            $this->invoiceTools(),
            $this->financeTools(),
            $this->inventoryTools(),
        );
    }

    // --- Customers (Kunden) -------------------------------------------------

    /** @return list<McpTool> */
    private function customerTools(): array
    {
        $customerUrl = static fn (McpToolContext $ctx, string $id): string => $ctx->url('/kunden/' . rawurlencode($id) . '/bearbeiten');
        $detail = fn (array $data, McpToolContext $ctx): array => self::compact($data) + ['url' => $customerUrl($ctx, (string) $data['id'])];

        return [
            new McpTool(
                'search_customers',
                'Search customers',
                'Searches customers (Kunden) by name, company, customer number, e-mail, phone or address. Without "query" all customers are listed. Returns id, customer number, display name and contact data.',
                McpSchemas::object(['query' => ['type' => 'string', 'description' => 'Search text (case-insensitive substring).']] + McpSchemas::paging()),
                [S::CustomersRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'customers', 'index', ['sort' => 'display_name'], static fn (array $c): array => self::pick($c, ['id', 'customer_number', 'display_name', 'type', 'company_name', 'email', 'phone', 'city']) + ['url' => $customerUrl($ctx, (string) $c['id'])]),
            ),
            new McpTool(
                'get_customer',
                'Get customer',
                'Returns one customer with address, contact data and contact persons (Ansprechpartner).',
                McpSchemas::object(['customer_id' => McpSchemas::id('Customer id (see search_customers).')], ['customer_id']),
                [S::CustomersRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $detail($this->api($ctx, 'customers', 'show', ['id' => self::str($args, 'customer_id')])['data'], $ctx),
            ),
            new McpTool(
                'create_customer',
                'Create customer',
                'Creates a customer. "type" is "privat" (private person) or "geschaeftlich" (business; then set company_name). Search first (search_customers) to avoid duplicates.',
                McpSchemas::input('CustomerInput'),
                [S::CustomersWrite],
                false,
                fn (array $args, McpToolContext $ctx): array => $detail($this->api($ctx, 'customers', 'store', [], [], $args)['data'], $ctx),
            ),
            new McpTool(
                'update_customer',
                'Update customer',
                'Changes a customer. Only the fields sent are changed. Sending "contacts" replaces all contact persons.',
                McpSchemas::input('CustomerPatch', ['customer_id' => McpSchemas::id('Customer id.')], ['customer_id']),
                [S::CustomersWrite],
                false,
                fn (array $args, McpToolContext $ctx): array => $detail($this->api($ctx, 'customers', 'update', ['id' => self::str($args, 'customer_id')], [], self::without($args, 'customer_id'))['data'], $ctx),
                idempotent: true,
            ),
        ];
    }

    // --- Articles (Artikel) and availability --------------------------------

    /** @return list<McpTool> */
    private function articleTools(): array
    {
        $articleUrl = static fn (McpToolContext $ctx, string $id): string => $ctx->url('/artikel/' . rawurlencode($id));

        return [
            new McpTool(
                'search_articles',
                'Search articles',
                'Searches the rental equipment catalogue (Artikel: speakers, lights, cables, …) by name, description or prefix. Returns id, name, manufacturer, category, daily rental price (EUR) and number of units in stock. Only active articles unless include_inactive is true.',
                McpSchemas::object([
                    'query' => ['type' => 'string', 'description' => 'Search text (case-insensitive substring).'],
                    'category_id' => McpSchemas::id('Only articles of this category.'),
                    'manufacturer_id' => McpSchemas::id('Only articles of this manufacturer.'),
                    'include_inactive' => ['type' => 'boolean', 'default' => false],
                ] + McpSchemas::paging()),
                [S::ArticlesRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'articles', 'index', array_filter([
                    'sort' => 'name',
                    'category_id' => self::optStr($args, 'category_id'),
                    'manufacturer_id' => self::optStr($args, 'manufacturer_id'),
                    'active' => self::bool($args, 'include_inactive') ? null : '1',
                ], static fn ($v) => $v !== null), static fn (array $a): array => self::compact(self::pick($a, ['id', 'name', 'manufacturer', 'category', 'daily_rental_price', 'stock_count', 'active', 'is_bundle'])) + ['url' => $articleUrl($ctx, (string) $a['id'])]),
            ),
            new McpTool(
                'get_article',
                'Get article',
                'Returns one article with all details and its inventory numbers (Inventurnummern, the individual units).',
                McpSchemas::object(['article_id' => McpSchemas::id('Article id (see search_articles).')], ['article_id']),
                [S::ArticlesRead],
                true,
                function (array $args, McpToolContext $ctx) use ($articleUrl): array {
                    $data = $this->api($ctx, 'articles', 'show', ['id' => self::str($args, 'article_id')])['data'];
                    unset($data['images']);

                    return self::compact($data) + ['url' => $articleUrl($ctx, (string) $data['id'])];
                },
            ),
            new McpTool(
                'check_availability',
                'Check availability',
                'Checks how many units of one or more articles are available (Verfügbarkeit) in a period, taking all scheduled events into account. "available" is the minimum over the whole period; for periods up to ' . self::MAX_CALENDAR_DAYS_WITH_DETAILS . ' days the available units per day are listed as well.',
                McpSchemas::object([
                    'article_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 100, 'description' => 'Article ids (see search_articles).'],
                    'from' => McpSchemas::date('First day'),
                    'to' => McpSchemas::date('Last day; defaults to "from"'),
                ], ['article_ids', 'from']),
                [S::ArticlesRead],
                true,
                function (array $args, McpToolContext $ctx): array {
                    $ids = array_values(array_filter(array_map('strval', (array) ($args['article_ids'] ?? [])), static fn (string $id) => trim($id) !== ''));
                    $json = $this->api($ctx, 'articles', 'availabilityBulk', [], array_filter([
                        'article_ids' => implode(',', $ids),
                        'from' => self::str($args, 'from'),
                        'to' => self::optStr($args, 'to'),
                    ], static fn ($v) => $v !== null));
                    $items = [];
                    foreach ($json['data'] ?? [] as $row) {
                        $item = self::pick($row, ['article_id', 'from', 'to', 'total', 'available']);
                        $days = (array) ($row['days'] ?? []);
                        if (count($days) > 1 && count($days) <= self::MAX_CALENDAR_DAYS_WITH_DETAILS) {
                            $item['available_per_day'] = array_column($days, 'available', 'date');
                        }
                        $items[] = $item;
                    }

                    return ['items' => $items];
                },
            ),
        ];
    }

    // --- Events (Veranstaltungen / rentals) ---------------------------------

    /** @return list<McpTool> */
    private function rentalTools(): array
    {
        $rentalUrl = static fn (McpToolContext $ctx, string $id): string => $ctx->url('/veranstaltungen/' . rawurlencode($id));
        $summary = static fn (array $r, McpToolContext $ctx): array => self::compact(self::pick($r, ['id', 'event_name', 'start_date', 'end_date', 'event_location', 'customer', 'deployment_days', 'is_tour', 'material'])) + ['url' => $rentalUrl($ctx, (string) $r['id'])];
        $detail = function (McpToolContext $ctx, string $id) use ($rentalUrl): array {
            $data = $this->api($ctx, 'rentals', 'show', ['id' => $id])['data'];
            $data['technicians'] = array_map(
                static fn (array $row): array => self::compact(['user' => self::pick((array) ($row['user'] ?? []), ['id', 'name', 'display_name', 'email']), 'trade_names' => $row['trade_names'] ?? []] + self::pick($row, ['status', 'role', 'note'])),
                $this->api($ctx, 'rentals', 'technicians', ['id' => $id])['data'] ?? [],
            );

            return self::compact($data) + ['url' => $rentalUrl($ctx, $id)];
        };

        return [
            new McpTool(
                'list_rentals',
                'List events',
                'Lists events (Veranstaltungen, i.e. rentals/jobs), newest start date first. Filter by search text (event name, location) or customer. For a date range use get_calendar.',
                McpSchemas::object([
                    'query' => ['type' => 'string', 'description' => 'Search text (case-insensitive substring of event name, location, …).'],
                    'customer_id' => McpSchemas::id('Only events of this customer.'),
                ] + McpSchemas::paging()),
                [S::RentalsRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'rentals', 'index', array_filter(['sort' => '-start_date', 'customer_id' => self::optStr($args, 'customer_id')], static fn ($v) => $v !== null), static fn (array $r): array => $summary($r, $ctx)),
            ),
            new McpTool(
                'get_calendar',
                'Event calendar',
                'Lists the events overlapping a period (Kalender), with the scheduled material per event. Use it for questions like "what is happening next weekend?".',
                McpSchemas::object([
                    'from' => McpSchemas::date('First day'),
                    'to' => McpSchemas::date('Last day; defaults to 30 days after "from"'),
                ] + McpSchemas::paging(), ['from']),
                [S::RentalsRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'rentals', 'calendar', array_filter(['sort' => 'start_date', 'from' => self::str($args, 'from'), 'to' => self::optStr($args, 'to')], static fn ($v) => $v !== null), static fn (array $r): array => $summary($r, $ctx)),
            ),
            new McpTool(
                'get_rental',
                'Get event',
                'Returns one event (Veranstaltung) with customer, scheduled items (articles and quantities), issued units, logistics (pickup/return) and assigned technicians.',
                McpSchemas::object(['rental_id' => McpSchemas::id('Event id (see list_rentals / get_calendar).')], ['rental_id']),
                [S::RentalsRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $detail($ctx, self::str($args, 'rental_id')),
            ),
            new McpTool(
                'create_rental',
                'Create event',
                'Creates an event (Veranstaltung) for a customer. Dates as YYYY-MM-DD; deployment_days is the number of billable rental days. Add material afterwards with add_rental_items. Check availability first with check_availability.',
                McpSchemas::input('RentalInput'),
                [S::RentalsWrite],
                false,
                function (array $args, McpToolContext $ctx) use ($detail): array {
                    $data = $this->api($ctx, 'rentals', 'store', [], [], $args)['data'];

                    return $detail($ctx, (string) $data['id']);
                },
            ),
            new McpTool(
                'update_rental',
                'Update event',
                'Changes an event. Only the fields sent are changed. Pickup/return details (warehouse, times, transport note) go into "logistics".',
                McpSchemas::input('RentalPatch', [
                    'rental_id' => McpSchemas::id('Event id.'),
                    'logistics' => McpSchemas::input('RentalLogisticsPatch') + ['description' => 'Pickup/return: warehouse ids, times (ISO 8601) and transport note. Only the fields sent are changed.'],
                ], ['rental_id']),
                [S::RentalsWrite],
                false,
                function (array $args, McpToolContext $ctx) use ($detail): array {
                    $id = self::str($args, 'rental_id');
                    $logistics = $args['logistics'] ?? null;
                    $fields = self::without(self::without($args, 'rental_id'), 'logistics');
                    if ($fields === [] && !is_array($logistics)) {
                        throw new McpToolError('Nothing to change: send at least one field.');
                    }
                    if ($fields !== []) {
                        $this->api($ctx, 'rentals', 'update', ['id' => $id], [], $fields);
                    }
                    if (is_array($logistics) && $logistics !== []) {
                        $this->api($ctx, 'rentals', 'updateLogistics', ['id' => $id], [], $logistics);
                    }

                    return $detail($ctx, $id);
                },
                idempotent: true,
            ),
            new McpTool(
                'add_rental_items',
                'Add material to event',
                'Schedules articles for an event (Positionen). Each entry adds the quantity of one article. Check availability first with check_availability.',
                McpSchemas::object([
                    'rental_id' => McpSchemas::id('Event id.'),
                    'items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => self::MAX_RENTAL_ITEMS_PER_CALL, 'items' => McpSchemas::object([
                        'article_id' => McpSchemas::id('Article id (see search_articles).'),
                        'quantity' => ['type' => ['integer', 'string'], 'description' => 'Whole number ≥ 1.'],
                    ], ['article_id', 'quantity'])],
                ], ['rental_id', 'items']),
                [S::RentalsWrite],
                false,
                function (array $args, McpToolContext $ctx) use ($detail): array {
                    $id = self::str($args, 'rental_id');
                    $added = 0;
                    foreach ((array) ($args['items'] ?? []) as $index => $item) {
                        if (!is_array($item)) {
                            throw new McpToolError('items[' . $index . '] must be an object with article_id and quantity.');
                        }
                        try {
                            $this->api($ctx, 'rentals', 'addItem', ['id' => $id], [], self::pick($item, ['article_id', 'quantity']));
                        } catch (McpToolError $e) {
                            throw new McpToolError('items[' . $index . ']: ' . $e->getMessage() . ($added > 0 ? ' (' . $added . ' item(s) before it were added.)' : ''), $e->fieldErrors, $e->logStatus);
                        }
                        $added++;
                    }

                    return ['added' => $added] + $detail($ctx, $id);
                },
            ),
            new McpTool(
                'update_rental_item',
                'Change item quantity',
                'Changes the quantity of a scheduled item of an event (the item id is listed under "items" in get_rental).',
                McpSchemas::input('RentalItemPatch', ['rental_item_id' => McpSchemas::id('Id of the event item.')], ['rental_item_id']),
                [S::RentalsWrite],
                false,
                function (array $args, McpToolContext $ctx): array {
                    $this->api($ctx, 'rentals', 'updateItem', ['id' => self::str($args, 'rental_item_id')], [], self::without($args, 'rental_item_id'));

                    return ['ok' => true, 'rental_item_id' => self::str($args, 'rental_item_id'), 'quantity' => $args['quantity'] ?? null];
                },
                idempotent: true,
            ),
            new McpTool(
                'list_minute_programs',
                'List minute programs',
                'Lists the minute programs (Minutenprogramme, run sheets of the show) of an event.',
                McpSchemas::object(['rental_id' => McpSchemas::id('Event id.')] + McpSchemas::paging(), ['rental_id']),
                [S::MinuteProgramsRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'rentals', 'minutePrograms', [], static fn (array $m): array => self::compact(self::pick($m, ['id', 'name', 'room', 'start_at'])) + ['url' => $ctx->url('/minutenprogramme/' . rawurlencode((string) $m['id']))], ['id' => self::str($args, 'rental_id')]),
            ),
        ];
    }

    // --- Offers & order confirmations (Angebote, Auftragsbestätigungen) -----

    /** @return list<McpTool> */
    private function salesTools(): array
    {
        $offerUrl = static fn (McpToolContext $ctx, string $id): string => $ctx->url('/angebote/' . rawurlencode($id));
        $offerDetail = fn (array $data, McpToolContext $ctx): array => self::compact($data) + ['url' => $offerUrl($ctx, (string) $data['id'])];
        $documentUrls = [
            'order_confirmation' => '/auftragsbestaetigungen/',
            'rental' => '/veranstaltungen/',
            'invoice_draft' => '/rechnungen/',
        ];

        return [
            new McpTool(
                'list_offers',
                'List offers',
                'Lists offers (Angebote), newest first. Status: draft, sent, accepted, declined, expired.',
                McpSchemas::object([
                    'status' => ['type' => 'string', 'enum' => ['draft', 'sent', 'accepted', 'declined', 'expired']],
                    'query' => ['type' => 'string', 'description' => 'Search text (offer number, location, note, …).'],
                ] + McpSchemas::paging()),
                [S::SalesRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'offers', 'index', array_filter(['sort' => '-created_at', 'status' => self::optStr($args, 'status')], static fn ($v) => $v !== null), static fn (array $o): array => self::compact(self::pick($o, ['id', 'offer_number', 'status', 'customer', 'start_date', 'end_date', 'valid_until', 'event_location', 'converted_rental_id'])) + ['url' => $offerUrl($ctx, (string) $o['id'])]),
            ),
            new McpTool(
                'get_offer',
                'Get offer',
                'Returns one offer with customer, items (rental, labor, sale, free text), discounts and totals (net/tax/gross in EUR).',
                McpSchemas::object(['offer_id' => McpSchemas::id('Offer id (see list_offers).')], ['offer_id']),
                [S::SalesRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $offerDetail($this->api($ctx, 'offers', 'show', ['id' => self::str($args, 'offer_id')])['data'], $ctx),
            ),
            new McpTool(
                'create_offer',
                'Create offer',
                'Creates an offer (Angebot) as draft. Rental items need article_id and quantity; without unit_price the article\'s daily rental price is used. Prices are per day in EUR; the number of billable days follows from the period. Check availability first with check_availability.',
                McpSchemas::optional(McpSchemas::input('OfferInput'), ['note']),
                [S::SalesWrite],
                false,
                fn (array $args, McpToolContext $ctx): array => $offerDetail($this->api($ctx, 'offers', 'store', [], [], $this->withArticlePrices($ctx, $args + ['note' => ''], true))['data'], $ctx),
            ),
            new McpTool(
                'update_offer',
                'Update offer',
                'Changes an offer. Only the fields sent are changed; an item list that is sent replaces that whole list (send all rental items, not just new ones).',
                McpSchemas::input('OfferPatch', ['offer_id' => McpSchemas::id('Offer id.')], ['offer_id']),
                [S::SalesWrite],
                false,
                fn (array $args, McpToolContext $ctx): array => $offerDetail($this->api($ctx, 'offers', 'update', ['id' => self::str($args, 'offer_id')], [], $this->withArticlePrices($ctx, self::without($args, 'offer_id'), false))['data'], $ctx),
                idempotent: true,
            ),
            new McpTool(
                'set_offer_status',
                'Set offer status',
                'Marks an offer as sent (does NOT send an e-mail), accepted or declined.',
                McpSchemas::object([
                    'offer_id' => McpSchemas::id('Offer id.'),
                    'status' => ['type' => 'string', 'enum' => ['sent', 'accepted', 'declined']],
                ], ['offer_id', 'status']),
                [S::SalesWrite],
                false,
                function (array $args, McpToolContext $ctx) use ($offerDetail): array {
                    $action = match (self::str($args, 'status')) {
                        'sent' => 'markSent',
                        'accepted' => 'accept',
                        'declined' => 'decline',
                        default => throw new McpToolError('status must be one of: sent, accepted, declined.'),
                    };
                    $this->api($ctx, 'offers', $action, ['id' => self::str($args, 'offer_id')], [], []);

                    return $offerDetail($this->api($ctx, 'offers', 'show', ['id' => self::str($args, 'offer_id')])['data'], $ctx);
                },
                idempotent: true,
            ),
            new McpTool(
                'convert_offer',
                'Convert offer',
                'Creates a follow-up document from an offer: "order_confirmation" (Auftragsbestätigung), "rental" (event with the offered material) or "invoice_draft" (invoice draft without invoice number; it is not finalized).',
                McpSchemas::object([
                    'offer_id' => McpSchemas::id('Offer id.'),
                    'target' => ['type' => 'string', 'enum' => array_keys($documentUrls)],
                ], ['offer_id', 'target']),
                [S::SalesRead],
                false,
                function (array $args, McpToolContext $ctx) use ($documentUrls): array {
                    $target = self::str($args, 'target');
                    [$action, $scopes] = match ($target) {
                        'order_confirmation' => ['createOrderConfirmation', [S::SalesWrite]],
                        'rental' => ['convertToRental', [S::SalesWrite, S::RentalsWrite]],
                        'invoice_draft' => ['createInvoice', [S::SalesRead, S::InvoicesWrite]],
                        default => throw new McpToolError('target must be one of: ' . implode(', ', array_keys($documentUrls)) . '.'),
                    };
                    $ctx->requireScopes(...$scopes);
                    $data = $this->api($ctx, 'offers', $action, ['id' => self::str($args, 'offer_id')], [], [])['data'];

                    return ['created' => $target] + self::compact($data) + ['url' => $ctx->url($documentUrls[$target] . rawurlencode((string) $data['id']))];
                },
            ),
            new McpTool(
                'list_order_confirmations',
                'List order confirmations',
                'Lists order confirmations (Auftragsbestätigungen), newest first. Status: draft, sent, confirmed, cancelled.',
                McpSchemas::object([
                    'status' => ['type' => 'string', 'enum' => ['draft', 'sent', 'confirmed', 'cancelled']],
                    'query' => ['type' => 'string', 'description' => 'Search text.'],
                ] + McpSchemas::paging()),
                [S::SalesRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'order-confirmations', 'index', array_filter(['sort' => '-created_at', 'status' => self::optStr($args, 'status')], static fn ($v) => $v !== null), static fn (array $o): array => self::compact(self::pick($o, ['id', 'order_confirmation_number', 'status', 'customer', 'start_date', 'end_date', 'event_location', 'source_offer_id'])) + ['url' => $ctx->url('/auftragsbestaetigungen/' . rawurlencode((string) $o['id']))]),
            ),
        ];
    }

    // --- Invoices (Rechnungen) ----------------------------------------------

    /** @return list<McpTool> */
    private function invoiceTools(): array
    {
        $invoiceUrl = static fn (McpToolContext $ctx, string $id): string => $ctx->url('/rechnungen/' . rawurlencode($id));
        $today = $this->now()->format('Y-m-d');
        $isOverdue = static fn (array $i): bool => ($i['status'] ?? null) === 'sent' && is_string($i['due_date'] ?? null) && substr($i['due_date'], 0, 10) < $today;
        $summary = static fn (array $i, McpToolContext $ctx): array => self::compact(self::pick($i, ['id', 'invoice_number', 'status', 'customer', 'invoice_date', 'due_date', 'paid_at', 'start_date', 'end_date', 'event_location'])) + ['overdue' => $isOverdue($i), 'url' => $invoiceUrl($ctx, (string) $i['id'])];

        return [
            new McpTool(
                'list_invoices',
                'List invoices',
                'Lists invoices (Rechnungen), newest first. Status: draft (no invoice number yet), sent (issued, unpaid), paid, cancelled, or "overdue" (issued, unpaid and past the due date).',
                McpSchemas::object([
                    'status' => ['type' => 'string', 'enum' => ['draft', 'sent', 'paid', 'cancelled', 'overdue']],
                    'query' => ['type' => 'string', 'description' => 'Search text (invoice number, location, note, …).'],
                ] + McpSchemas::paging()),
                [S::InvoicesRead],
                true,
                function (array $args, McpToolContext $ctx) use ($summary, $isOverdue): array {
                    $status = self::optStr($args, 'status');
                    if ($status !== 'overdue') {
                        return $this->listing($ctx, $args, 'invoices', 'index', array_filter(['sort' => '-created_at', 'status' => $status], static fn ($v) => $v !== null), static fn (array $i): array => $summary($i, $ctx));
                    }
                    // Overdue is not a stored status: filter the issued invoices by due date.
                    [$limit, $page] = self::paging($args);
                    $json = $this->api($ctx, 'invoices', 'index', [], array_filter(['status' => 'sent', 'sort' => 'due_date', 'page_size' => '500', 'q' => self::optStr($args, 'query')], static fn ($v) => $v !== null));
                    $overdue = array_values(array_filter($json['data'] ?? [], $isOverdue));

                    return [
                        'items' => array_map(static fn (array $i): array => $summary($i, $ctx), array_slice($overdue, ($page - 1) * $limit, $limit)),
                        'page' => $page,
                        'limit' => $limit,
                        'total' => count($overdue),
                        'has_more' => $page * $limit < count($overdue),
                    ];
                },
            ),
            new McpTool(
                'get_invoice',
                'Get invoice',
                'Returns one invoice with customer, items, totals (net/tax/gross in EUR), payment status and dunning notices (Mahnungen).',
                McpSchemas::object(['invoice_id' => McpSchemas::id('Invoice id (see list_invoices).')], ['invoice_id']),
                [S::InvoicesRead],
                true,
                function (array $args, McpToolContext $ctx) use ($invoiceUrl, $isOverdue): array {
                    $id = self::str($args, 'invoice_id');
                    $data = $this->api($ctx, 'invoices', 'show', ['id' => $id])['data'];
                    $data['overdue'] = $isOverdue($data);
                    $data['dunning_notices'] = $this->api($ctx, 'invoices', 'dunningNotices', ['id' => $id])['data'] ?? [];

                    return self::compact($data) + ['url' => $invoiceUrl($ctx, $id)];
                },
            ),
            new McpTool(
                'create_invoice_draft_from_rental',
                'Create invoice draft from event',
                'Creates an invoice draft (Rechnungsentwurf, no invoice number yet) from an event with its material. Finalizing the invoice is only possible in the web interface.',
                McpSchemas::object(['rental_id' => McpSchemas::id('Event id.')], ['rental_id']),
                [S::RentalsRead, S::InvoicesWrite],
                false,
                function (array $args, McpToolContext $ctx) use ($invoiceUrl): array {
                    $data = $this->api($ctx, 'rentals', 'createInvoice', ['id' => self::str($args, 'rental_id')], [], [])['data'];

                    return self::compact($data) + ['url' => $invoiceUrl($ctx, (string) $data['id'])];
                },
            ),
            new McpTool(
                'mark_invoice_paid',
                'Mark invoice as paid',
                'Marks an issued invoice as paid (today). Only for issued invoices (status "sent").',
                McpSchemas::object(['invoice_id' => McpSchemas::id('Invoice id.')], ['invoice_id']),
                [S::InvoicesWrite],
                false,
                function (array $args, McpToolContext $ctx) use ($summary): array {
                    $data = $this->api($ctx, 'invoices', 'markPaid', ['id' => self::str($args, 'invoice_id')], [], [])['data'];

                    return $summary($data, $ctx);
                },
            ),
        ];
    }

    // --- Finance and reports ------------------------------------------------

    /** @return list<McpTool> */
    private function financeTools(): array
    {
        return [
            new McpTool(
                'list_receipts',
                'List receipts',
                'Lists receipts (Belege, incoming invoices), newest first, with amount (EUR), category and payment status.',
                McpSchemas::object([
                    'query' => ['type' => 'string', 'description' => 'Search text (receipt number, note, …).'],
                    'supplier_id' => McpSchemas::id('Only receipts of this supplier.'),
                ] + McpSchemas::paging()),
                [S::FinanceRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'finance', 'receipts', array_filter(['sort' => '-receipt_date', 'supplier_id' => self::optStr($args, 'supplier_id')], static fn ($v) => $v !== null), static fn (array $r): array => self::compact(self::pick($r, ['id', 'receipt_number', 'receipt_date', 'amount', 'category', 'status', 'paid_at', 'note', 'supplier'])) + ['url' => $ctx->url('/belege/' . rawurlencode((string) $r['id']))]),
            ),
            new McpTool(
                'list_purchase_orders',
                'List purchase orders',
                'Lists purchase orders to suppliers (Bestellungen), newest first.',
                McpSchemas::object(['query' => ['type' => 'string', 'description' => 'Search text.']] + McpSchemas::paging()),
                [S::FinanceRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'finance', 'purchaseOrders', ['sort' => '-created_at'], static fn (array $p): array => self::compact(self::pick($p, ['id', 'status', 'ordered_at', 'note', 'supplier', 'created_at'])) + ['url' => $ctx->url('/bestellungen/' . rawurlencode((string) $p['id']))]),
            ),
            new McpTool(
                'get_report',
                'Get report',
                'Returns a report: "income_expense" (Einnahmen/Ausgaben of a year), "revenue_forecast" (expected revenue for several years from "from_year") or "depreciation" (AfA, depreciation of a year). Amounts in EUR.',
                McpSchemas::object([
                    'report' => ['type' => 'string', 'enum' => ['income_expense', 'revenue_forecast', 'depreciation']],
                    'year' => ['type' => 'integer', 'description' => 'Year (income_expense, depreciation); defaults to the current year.'],
                    'from_year' => ['type' => 'integer', 'description' => 'First year (revenue_forecast); defaults to the current year.'],
                    'years' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'description' => 'Number of years (revenue_forecast, default 5).'],
                ], ['report']),
                [S::ReportsRead],
                true,
                function (array $args, McpToolContext $ctx): array {
                    $report = self::str($args, 'report');
                    $action = match ($report) {
                        'income_expense' => 'incomeExpense',
                        'revenue_forecast' => 'revenueForecast',
                        'depreciation' => 'depreciation',
                        default => throw new McpToolError('report must be one of: income_expense, revenue_forecast, depreciation.'),
                    };
                    $query = array_filter([
                        'year' => self::optStr($args, 'year'),
                        'from_year' => self::optStr($args, 'from_year'),
                        'years' => self::optStr($args, 'years'),
                    ], static fn ($v) => $v !== null);

                    return ['report' => $report] + self::compact($this->api($ctx, 'reports', $action, [], $query)['data'] ?? []);
                },
            ),
        ];
    }

    // --- Inventory & equipment (Lager & Technik) ---------------------------

    /** @return list<McpTool> */
    private function inventoryTools(): array
    {
        return [
            new McpTool(
                'get_inventory_number',
                'Get inventory number',
                'Returns one unit of an article (Inventurnummer) by id or by its number as printed on the label, with article, storage location, current value and — with the inventory:read scope — its electrical inspections (E-Prüfungen, DGUV V3).',
                McpSchemas::object([
                    'inventory_number_id' => McpSchemas::id('Id of the inventory number.'),
                    'number' => ['type' => 'string', 'description' => 'The inventory number itself, e.g. as on the label (alternative to inventory_number_id).'],
                ]),
                [S::ArticlesRead],
                true,
                function (array $args, McpToolContext $ctx): array {
                    $id = self::optStr($args, 'inventory_number_id');
                    $number = self::optStr($args, 'number');
                    if ($id === null && $number !== null) {
                        $id = $this->inventoryNumbers?->findByNumber(trim($number))?->id
                            ?? throw new McpToolError('Inventory number "' . $number . '" was not found.', logStatus: 404);
                    }
                    if ($id === null) {
                        throw new McpToolError('Send inventory_number_id or number.');
                    }
                    $data = $this->api($ctx, 'articles', 'showInventoryNumber', ['id' => $id])['data'];
                    if ($ctx->allows(S::InventoryRead)) {
                        $data['electrical_inspections'] = $this->api($ctx, 'inventory', 'inventoryNumberInspections', ['id' => $id], ['sort' => '-inspected_at', 'page_size' => '20'])['data'] ?? [];
                    }
                    $articleId = is_array($data['article'] ?? null) ? (string) ($data['article']['id'] ?? '') : '';

                    return self::compact($data) + ($articleId !== '' ? ['url' => $ctx->url('/artikel/' . rawurlencode($articleId))] : []);
                },
            ),
            new McpTool(
                'list_rmas',
                'List RMAs',
                'Lists repairs/returns to manufacturers (RMA). Status: offen (not yet sent), unterwegs (sent, not back yet), abgeschlossen (returned).',
                McpSchemas::object([
                    'status' => ['type' => 'string', 'enum' => ['offen', 'unterwegs', 'abgeschlossen']],
                    'query' => ['type' => 'string', 'description' => 'Search text (reason, service provider, reference, …).'],
                ] + McpSchemas::paging()),
                [S::InventoryRead],
                true,
                fn (array $args, McpToolContext $ctx): array => $this->listing($ctx, $args, 'inventory', 'rmas', array_filter(['sort' => '-created_at', 'status' => self::optStr($args, 'status')], static fn ($v) => $v !== null), static fn (array $r): array => self::compact($r)),
            ),
            new McpTool(
                'record_electrical_inspection',
                'Record electrical inspection',
                'Records an electrical inspection (E-Prüfung, DGUV V3) of a unit. inspected_at defaults to today; validity_years is 1 or 2 (years until the next inspection); measured values as decimal numbers.',
                McpSchemas::input('ElectricalInspectionInput', [
                    'inventory_number_id' => McpSchemas::id('Id of the inventory number (see get_inventory_number).'),
                    'validity_years' => ['type' => ['string', 'integer'], 'enum' => ['1', '2', 1, 2], 'description' => 'Years until the next inspection.'],
                ], ['inventory_number_id', 'validity_years']),
                [S::InventoryWrite],
                false,
                fn (array $args, McpToolContext $ctx): array => self::compact($this->api($ctx, 'inventory', 'storeInspection', ['id' => self::str($args, 'inventory_number_id')], [], self::without($args, 'inventory_number_id') + ['inspected_at' => $this->now()->format('Y-m-d')])['data']),
            ),
        ];
    }

    // --- Bridge to the REST API controllers ----------------------------------

    /**
     * Calls an action of a REST API controller with the token of the MCP
     * request and returns the JSON body. Errors become McpToolError.
     *
     * @param array<string, string> $params route parameters
     * @param array<string, string> $query
     * @param array<string, mixed>|null $body null = read request (GET)
     * @return array<string, mixed>
     */
    private function api(McpToolContext $ctx, string $controller, string $action, array $params = [], array $query = [], ?array $body = null): array
    {
        $instance = $this->controllers[$controller] ?? throw new RuntimeException('API controller "' . $controller . '" is not configured.');
        $request = new ApiRequest(
            method: $body === null ? 'GET' : 'POST',
            path: '/mcp',
            routeParams: $params,
            query: $query,
            body: $body === null ? [] : self::stringifyRows($body),
            files: [],
            token: $ctx->token,
        );

        try {
            $response = $instance->{$action}($request);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $message) {
                $errors[Normalizer::snake((string) $field)] = (string) $message;
            }
            throw new McpToolError('The input is invalid.', $errors, 422);
        } catch (ApiException $e) {
            throw new McpToolError($e->getMessage(), $e->errors, $e->status);
        } catch (NotFoundException $e) {
            throw new McpToolError($e->getMessage(), logStatus: 404);
        } catch (ConflictException $e) {
            throw new McpToolError($e->getMessage(), logStatus: 409);
        }
        if (!$response instanceof ApiResponse) {
            throw new LogicException('API controller ' . $controller . '::' . $action . ' did not return an ApiResponse.');
        }
        if ($response->status >= 400) {
            $json = $response->json ?? [];
            throw new McpToolError((string) ($json['error'] ?? 'Request failed.'), (array) ($json['errors'] ?? []), $response->status);
        }

        return $response->json ?? [];
    }

    /**
     * Runs a list action and reshapes it to {items, page, limit, total, has_more}.
     *
     * @param array<string, mixed> $args tool arguments (query, limit, page)
     * @param array<string, string> $query additional query parameters
     * @param callable(array<string, mixed>): array<string, mixed> $map
     * @param array<string, string> $params route parameters
     * @return array<string, mixed>
     */
    private function listing(McpToolContext $ctx, array $args, string $controller, string $action, array $query, callable $map, array $params = []): array
    {
        [$limit, $page] = self::paging($args);
        $search = self::optStr($args, 'query');
        $json = $this->api($ctx, $controller, $action, $params, $query + array_filter([
            'q' => $search,
            'page' => (string) $page,
            'page_size' => (string) $limit,
        ], static fn ($v) => $v !== null));
        $meta = (array) ($json['meta'] ?? []);
        $items = array_map(static fn (array $item): array => (array) self::compact($map($item)), array_values((array) ($json['data'] ?? [])));

        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => (int) ($meta['total'] ?? count($items)),
            'has_more' => $page < (int) ($meta['total_pages'] ?? 1),
        ];
    }

    /**
     * Fills missing unit prices of rental items with the article's daily
     * rental price (needs articles:read; otherwise the command reports the
     * missing price as usual).
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function withArticlePrices(McpToolContext $ctx, array $args, bool $required): array
    {
        if (!isset($args['items']) || !is_array($args['items']) || !$ctx->allows(S::ArticlesRead)) {
            return $args;
        }
        $prices = [];
        foreach ($args['items'] as $index => $item) {
            if (!is_array($item) || !is_string($item['article_id'] ?? null) || (isset($item['unit_price']) && $item['unit_price'] !== '')) {
                continue;
            }
            $articleId = $item['article_id'];
            if (!array_key_exists($articleId, $prices)) {
                $price = $this->api($ctx, 'articles', 'show', ['id' => $articleId])['data']['daily_rental_price'] ?? null;
                $prices[$articleId] = is_scalar($price) ? (string) $price : null;
            }
            if ($prices[$articleId] !== null) {
                $args['items'][$index]['unit_price'] = $prices[$articleId];
            } elseif ($required) {
                throw new McpToolError('items[' . $index . ']: the article has no daily rental price — please send unit_price.');
            }
        }

        return $args;
    }

    // --- Helpers ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     * @return array{int, int} [limit, page]
     */
    public static function paging(array $args): array
    {
        $limit = is_numeric($args['limit'] ?? null) ? (int) $args['limit'] : self::DEFAULT_LIMIT;
        $page = is_numeric($args['page'] ?? null) ? (int) $args['page'] : 1;

        return [max(1, min(self::MAX_LIMIT, $limit)), max(1, $page)];
    }

    /**
     * Drops null, empty strings and empty arrays (recursively) to keep results small.
     */
    public static function compact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $isList = array_is_list($value);
        $result = [];
        foreach ($value as $key => $item) {
            $item = self::compact($item);
            if ($item === null || $item === '' || $item === []) {
                continue;
            }
            $result[$key] = $item;
        }

        return $isList ? array_values($result) : $result;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public static function pick(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private static function without(array $args, string $key): array
    {
        unset($args[$key]);

        return $args;
    }

    /** @param array<string, mixed> $args */
    private static function str(array $args, string $key): string
    {
        $value = $args[$key] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new McpToolError('Argument "' . $key . '" is required.');
        }

        return trim((string) $value);
    }

    /** @param array<string, mixed> $args */
    private static function optStr(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;

        return is_scalar($value) && !is_bool($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /** @param array<string, mixed> $args */
    private static function bool(array $args, string $key): bool
    {
        return in_array($args[$key] ?? false, [true, 1, '1', 'true'], true);
    }

    /**
     * Item rows (lists of objects) are passed to the commands as they are;
     * the commands expect their scalar fields as strings, like a form post.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function stringifyRows(array $body): array
    {
        foreach ($body as $field => $value) {
            if (!is_array($value) || !array_is_list($value)) {
                continue;
            }
            foreach ($value as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($row as $key => $cell) {
                    if (is_bool($cell)) {
                        $row[$key] = $cell ? '1' : '0';
                    } elseif (is_int($cell) || is_float($cell)) {
                        $row[$key] = (string) $cell;
                    }
                }
                $body[$field][$index] = $row;
            }
        }

        return $body;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
