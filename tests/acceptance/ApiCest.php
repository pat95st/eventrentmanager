<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * REST API /api/v1 end to end (real HTTP → Router → ApiKernel → controller
 * → command/query bus → database): authentication, scopes, read-only
 * tokens, rate limiting, write log and one run through each area.
 */
final class ApiCest
{
    private const ALL_SCOPES = [
        'customers:read', 'customers:write', 'articles:read', 'articles:write', 'master_data:read', 'master_data:write',
        'rentals:read', 'rentals:write', 'sales:read', 'sales:write', 'invoices:read', 'invoices:write', 'invoices:finalize',
        'finance:read', 'finance:write', 'inventory:read', 'inventory:write', 'minute_programs:read', 'reports:read',
    ];

    public function disabledApiAnswers404(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['customers:read'], enableApi: false);
        [$status] = $I->sendApiRequest('GET', '/customers', $token);
        $I->assertSame(404, $status);
    }

    public function rejectsMissingOrUnknownToken(AcceptanceTester $I): void
    {
        $I->haveApiToken(['customers:read']);
        [$status] = $I->sendApiRequest('GET', '/customers', null);
        $I->assertSame(401, $status);
        [$status] = $I->sendApiRequest('GET', '/customers', 'mv_unknown');
        $I->assertSame(401, $status);
    }

    public function enforcesScopesAndReadOnly(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['articles:read', 'customers:write'], readOnly: true);

        [$status] = $I->sendApiRequest('GET', '/articles', $token);
        $I->assertSame(200, $status);

        [$status, $body] = $I->sendApiRequest('GET', '/customers', $token);
        $I->assertSame(403, $status);
        $I->assertStringContainsString('customers:read', $body['error']);

        // customers:write is granted but the token is read-only.
        [$status] = $I->sendApiRequest('POST', '/customers', $token, ['type' => 'privat']);
        $I->assertSame(403, $status);
        $I->seeInDatabase('api_request_log', ['method' => 'POST', 'path' => '/api/v1/customers', 'status_code' => 403]);
    }

    public function appliesTheRateLimit(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['articles:read'], rateLimitPerMinute: 2);
        // Stay inside one minute window.
        if ((int) date('s') > 55) {
            sleep(6);
        }
        $I->assertSame(200, $I->sendApiRequest('GET', '/articles', $token)[0]);
        $I->assertSame(200, $I->sendApiRequest('GET', '/articles', $token)[0]);
        [$status] = $I->sendApiRequest('GET', '/articles', $token);
        $I->assertSame(429, $status);
        $I->seeHttpHeader('Retry-After', '');
    }

    public function customerLifecycle(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['customers:read', 'customers:write']);

        [$status, $body] = $I->sendApiRequest('POST', '/customers', $token, [
            'type' => 'privat', 'first_name' => 'Erika', 'last_name' => 'Muster', 'street' => 'Hauptstr. 1',
            'postal_code' => '12345', 'city' => 'Berlin', 'phone' => null, 'email' => 'erika@example.org',
            'contacts' => [['name' => 'Max', 'email' => 'max@example.org']],
        ]);
        $I->assertSame(201, $status);
        $id = $body['data']['id'];
        $I->assertSame('Erika Muster', $body['data']['display_name']);

        [$status, $body] = $I->sendApiRequest('PATCH', '/customers/' . $id, $token, ['city' => 'Hamburg']);
        $I->assertSame(200, $status);
        $I->assertSame('Hamburg', $body['data']['city']);
        $I->assertSame('Muster', $body['data']['last_name'], 'PATCH keeps fields that are not sent');
        $I->assertCount(1, $body['data']['contacts'], 'PATCH keeps the contacts');

        [$status, $body] = $I->sendApiRequest('GET', '/customers?q=hamburg', $token);
        $I->assertSame(200, $status);
        $I->assertSame(1, $body['meta']['total']);

        [$status, $body] = $I->sendApiRequest('POST', '/customers', $token, ['first_name' => 'X']);
        $I->assertSame(422, $status);
        $I->assertArrayHasKey('last_name', $body['errors']);

        [$status] = $I->sendApiRequest('DELETE', '/customers/' . $id, $token);
        $I->assertSame(204, $status);
        [$status] = $I->sendApiRequest('GET', '/customers/' . $id, $token);
        $I->assertSame(404, $status);
    }

    public function rentalToPaidInvoice(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(self::ALL_SCOPES);

        [, $customer] = $I->sendApiRequest('POST', '/customers', $token, [
            'type' => 'privat', 'first_name' => 'Erika', 'last_name' => 'Muster', 'street' => 'Hauptstr. 1',
            'postal_code' => '12345', 'city' => 'Berlin', 'phone' => null, 'email' => null,
        ]);
        [, $manufacturer] = $I->sendApiRequest('POST', '/manufacturers', $token, ['name' => 'Shure', 'prefix' => 'SHU']);
        [$status, $article] = $I->sendApiRequest('POST', '/articles', $token, [
            'name' => 'SM58', 'manufacturer_id' => $manufacturer['data']['id'], 'prefix' => 'SM', 'category_id' => null,
            'description' => null, 'weight_value' => null, 'weight_unit' => null, 'dimensions' => null,
            'daily_rental_price' => '5.00', 'is_billable' => true,
        ]);
        $I->assertSame(201, $status);
        $articleId = $article['data']['id'];
        [$status] = $I->sendApiRequest('POST', '/articles/' . $articleId . '/inventory-numbers', $token, ['acquisition_date' => '2026-01-15']);
        $I->assertSame(201, $status);

        [$status, $rental] = $I->sendApiRequest('POST', '/rentals', $token, [
            'customer_id' => $customer['data']['id'], 'start_date' => '2026-12-01', 'end_date' => '2026-12-02',
            'event_name' => 'Feier', 'event_location' => 'Halle', 'deployment_days' => '1',
        ]);
        $I->assertSame(201, $status);
        $rentalId = $rental['data']['id'];
        [$status] = $I->sendApiRequest('POST', '/rentals/' . $rentalId . '/items', $token, ['article_id' => $articleId, 'quantity' => '1']);
        $I->assertSame(201, $status);

        [, $availability] = $I->sendApiRequest('GET', '/articles/' . $articleId . '/availability?from=2026-12-01&to=2026-12-02', $token);
        $I->assertSame(0, $availability['data']['available']);

        [$status, $invoice] = $I->sendApiRequest('POST', '/rentals/' . $rentalId . '/invoice', $token);
        $I->assertSame(201, $status);
        $invoiceId = $invoice['data']['id'];
        $I->assertSame('draft', $invoice['data']['status']);

        [$status, $invoice] = $I->sendApiRequest('PATCH', '/invoices/' . $invoiceId, $token, ['note' => 'Danke!']);
        $I->assertSame(200, $status);
        $I->assertCount(1, $invoice['data']['items'], 'PATCH keeps the positions');

        [$status, $invoice] = $I->sendApiRequest('POST', '/invoices/' . $invoiceId . '/finalize', $token);
        $I->assertSame(200, $status);
        $I->assertSame('sent', $invoice['data']['status']);
        $I->assertNotEmpty($invoice['data']['invoice_number']);

        [$status] = $I->sendApiRequest('PATCH', '/invoices/' . $invoiceId, $token, ['note' => 'zu spät']);
        $I->assertSame(409, $status);

        [$status, $invoice] = $I->sendApiRequest('POST', '/invoices/' . $invoiceId . '/mark-paid', $token);
        $I->assertSame(200, $status);
        $I->assertSame('paid', $invoice['data']['status']);

        $I->sendApiRequest('GET', '/invoices/' . $invoiceId . '/pdf', $token);
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Type', 'application/pdf');
        $I->sendApiRequest('GET', '/invoices/' . $invoiceId . '/xrechnung', $token);
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Type', 'application/xml');
    }

    public function finalizingNeedsItsOwnScope(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['invoices:read', 'invoices:write']);
        [$status, $body] = $I->sendApiRequest('POST', '/invoices/00000000-0000-4000-8000-000000000000/finalize', $token);
        $I->assertSame(403, $status);
        $I->assertStringContainsString('invoices:finalize', $body['error']);
    }

    public function everyListEndpointAnswers(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(self::ALL_SCOPES);
        $paths = [
            '/customers', '/articles', '/article-sets', '/categories', '/manufacturers', '/labor-time-articles',
            '/suppliers', '/venues', '/warehouses', '/vehicles', '/rentals', '/calendar?from=2026-11-01',
            '/offers', '/order-confirmations', '/invoices', '/invoice-corrections', '/receipts', '/bank-accounts',
            '/purchase-orders', '/cases', '/racks', '/rmas', '/electrical-inspections', '/sales',
            '/minute-program-templates', '/reports/income-expense?year=2026', '/reports/revenue-forecast?from_year=2026',
            '/reports/depreciation?year=2026',
        ];
        foreach ($paths as $path) {
            [$status, $body] = $I->sendApiRequest('GET', $path, $token);
            $I->assertSame(200, $status, $path . ': ' . json_encode($body));
            $I->assertArrayHasKey('data', $body, $path);
        }
    }
}
