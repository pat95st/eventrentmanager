<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * MCP server /mcp end to end (real HTTP → Router → McpServer → tool →
 * REST API controller → command/query bus → database): switches, token
 * release, tool filtering, a customer/event/offer run and the log.
 */
final class McpCest
{
    public function answers404UntilMcpIsSwitchedOn(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['customers:read'], mcpAllowed: true);
        [$status] = $I->sendMcpRequest($token, 'ping');
        $I->assertSame(404, $status);

        $I->updateInDatabase('api_settings', ['mcp_enabled' => 1], ['id' => 1]);
        [$status, $body] = $I->sendMcpRequest($token, 'ping');
        $I->assertSame(200, $status);
        $I->assertSame([], $body['result']);
    }

    public function requiresATokenReleasedForMcp(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['customers:read']);
        $I->updateInDatabase('api_settings', ['mcp_enabled' => 1], ['id' => 1]);

        $I->assertSame(401, $I->sendMcpRequest(null, 'ping')[0]);
        $I->assertSame(403, $I->sendMcpRequest($token, 'ping')[0]);
    }

    public function listsOnlyPermittedToolsAndHonoursReadOnly(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['customers:read', 'customers:write'], mcpAllowed: true);
        $I->updateInDatabase('api_settings', ['mcp_enabled' => 1], ['id' => 1]);

        [, $body] = $I->sendMcpRequest($token, 'tools/list');
        $I->assertSame(['search_customers', 'get_customer', 'create_customer', 'update_customer'], array_column($body['result']['tools'], 'name'));

        $I->updateInDatabase('api_settings', ['mcp_read_only' => 1], ['id' => 1]);
        [, $body] = $I->sendMcpRequest($token, 'tools/list');
        $I->assertSame(['search_customers', 'get_customer'], array_column($body['result']['tools'], 'name'));
        [$isError, $text] = $I->callMcpTool($token, 'create_customer', ['type' => 'privat']);
        $I->assertTrue($isError);
        $I->assertStringContainsString('read only', $text);
    }

    public function plansAnEventAndAnOffer(AcceptanceTester $I): void
    {
        $token = $I->haveApiToken(['customers:read', 'customers:write', 'articles:read', 'articles:write', 'master_data:write', 'rentals:read', 'rentals:write', 'sales:read', 'sales:write'], mcpAllowed: true);
        $I->updateInDatabase('api_settings', ['mcp_enabled' => 1], ['id' => 1]);

        // Articles cannot be created via MCP: set one up through the REST API.
        [, $manufacturer] = $I->sendApiRequest('POST', '/manufacturers', $token, ['name' => 'Acme', 'prefix' => 'ACM']);
        [, $article] = $I->sendApiRequest('POST', '/articles', $token, ['name' => 'Topteil', 'manufacturer_id' => $manufacturer['data']['id'], 'prefix' => 'TOP', 'daily_rental_price' => '25.00']);
        $articleId = $article['data']['id'];
        $I->sendApiRequest('POST', '/articles/' . $articleId . '/inventory-numbers', $token, []);
        $I->sendApiRequest('POST', '/articles/' . $articleId . '/inventory-numbers', $token, []);

        [$isError, $customer] = $I->callMcpTool($token, 'create_customer', ['type' => 'privat', 'first_name' => 'Anna', 'last_name' => 'Müller', 'street' => 'Hauptstr. 1', 'postal_code' => '90402', 'city' => 'Nürnberg']);
        $I->assertFalse($isError, (string) json_encode($customer));
        $I->assertStringEndsWith('/kunden/' . $customer['id'] . '/bearbeiten', $customer['url']);

        [, $found] = $I->callMcpTool($token, 'search_customers', ['query' => 'müller']);
        $I->assertSame(1, $found['total']);

        [, $rental] = $I->callMcpTool($token, 'create_rental', ['customer_id' => $customer['id'], 'start_date' => '2030-06-01', 'end_date' => '2030-06-02', 'event_name' => 'Sommerfest', 'event_location' => 'Park', 'deployment_days' => '2']);
        [$isError] = $I->callMcpTool($token, 'add_rental_items', ['rental_id' => $rental['id'], 'items' => [['article_id' => $articleId, 'quantity' => 1]]]);
        $I->assertFalse($isError);

        [, $availability] = $I->callMcpTool($token, 'check_availability', ['article_ids' => [$articleId], 'from' => '2030-06-01', 'to' => '2030-06-02']);
        $I->assertSame(2, $availability['items'][0]['total']);
        $I->assertSame(1, $availability['items'][0]['available']);

        [$isError, $offer] = $I->callMcpTool($token, 'create_offer', ['customer_id' => $customer['id'], 'start_date' => '2030-07-01', 'end_date' => '2030-07-01', 'event_location' => 'Halle', 'valid_until' => '2030-06-15', 'items' => [['article_id' => $articleId, 'quantity' => 2]]]);
        $I->assertFalse($isError, (string) json_encode($offer));
        $I->assertSame('25.00', $offer['items'][0]['unit_price']);
        $I->seeInDatabase('offers', ['id' => $offer['id'], 'status' => 'draft']);

        [$isError, $text] = $I->callMcpTool($token, 'set_offer_status', ['offer_id' => $offer['id'], 'status' => 'accepted']);
        $I->assertTrue($isError, 'a draft cannot be accepted');
        $I->assertNotSame('', $text);

        $I->seeInDatabase('api_request_log', ['method' => 'MCP', 'status_code' => 200]);
        $I->seeInDatabase('api_request_log', ['method' => 'MCP', 'status_code' => 409]);
    }
}
