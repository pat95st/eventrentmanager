<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;
use Codeception\Module\PhpBrowser;

/**
 * Meldet sich als der von database/schema.sql geseedete Standard-
 * Administrator an (admin@materialverleih.local) UND erledigt dabei gleich
 * die erzwungene Erstanmeldungs-Passwortänderung
 * (AuthGuard::FORCED_PASSWORD_CHANGE_PATH, siehe
 * src/Infrastructure/Http/Auth/AuthGuard.php) — ohne dieses Hilfsmittel
 * müsste jeder einzelne Acceptance-Test, der eine angemeldete Sitzung
 * braucht, denselben zweistufigen Ablauf wiederholen, da das Db-Modul die
 * Datenbank (und damit must_change_password = 1 für den Seed-Admin) vor
 * JEDEM Test neu aus database/schema.sql lädt (siehe
 * tests/acceptance.suite.yml).
 */
class Acceptance extends Module
{
    public const ADMIN_EMAIL = 'admin@materialverleih.local';
    public const ADMIN_INITIAL_PASSWORD = 'ChangeMe!2026';
    public const ADMIN_TEST_PASSWORD = 'TestPasswort2026!';

    public function loginAsAdmin(): void
    {
        $browser = $this->phpBrowser();

        $browser->amOnPage('/login');
        $browser->submitForm('form', [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_INITIAL_PASSWORD,
        ]);

        // Erstanmeldung: erzwungene Passwortänderung, siehe Klassendocblock.
        // WICHTIG: bewusst NICHT der bare 'form'-Selektor — diese Seite
        // läuft (anders als /login, eine eigenständige Seite ohne Layout)
        // durch View::page() und damit durch layout.php, dessen Kopfzeile
        // bereits VOR dem eigentlichen Seiteninhalt ein eigenes <form
        // action="/logout"> enthält. Ein bare 'form' würde also fälschlich
        // DAS Formular treffen und den Test-User ausloggen statt das
        // Passwort zu ändern.
        $browser->seeCurrentUrlEquals('/profil/passwort');
        $browser->submitForm('form[action="/profil/passwort"]', [
            'current_password' => self::ADMIN_INITIAL_PASSWORD,
            'new_password' => self::ADMIN_TEST_PASSWORD,
        ]);

        // ProfileController::changePassword() leitet nach Erfolg auf '/'
        // weiter, die Startseite (Dashboard) — dort endet die Anmeldung.
        $browser->seeCurrentUrlEquals('/');
    }

    /**
     * Prüft einen Header der zuletzt empfangenen HTTP-Antwort. PhpBrowser
     * selbst bringt dafür keine Methode mit (seeHttpHeader() gibt es nur im
     * REST-Modul), CalDavProfileCest braucht sie für den Content-Type des
     * .mobileconfig-Downloads.
     */
    public function seeHttpHeader(string $name, string $expected): void
    {
        $actual = $this->phpBrowser()->client->getInternalResponse()->getHeader($name);

        $this->assertIsString($actual, "Antwort enthält keinen Header \"{$name}\".");
        $this->assertStringContainsString($expected, $actual);
    }

    /**
     * Creates an API token directly in the database (the settings form is
     * covered separately) and returns the raw token.
     *
     * @param list<string> $scopes
     */
    public function haveApiToken(array $scopes, bool $readOnly = false, ?int $rateLimitPerMinute = null, bool $enableApi = true, bool $mcpAllowed = false): string
    {
        $raw = 'mv_test' . bin2hex(random_bytes(8));
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule('Db');
        $db->haveInDatabase('api_tokens', [
            'id' => sprintf('%08x-0000-4000-8000-%012x', random_int(0, 0xffffffff), random_int(0, 0xffffffffffff)),
            'name' => 'Test ' . substr($raw, -4),
            'token_hash' => hash('sha256', $raw),
            'token_last_four' => substr($raw, -4),
            'enabled' => 1,
            'scopes' => json_encode($scopes),
            'read_only' => $readOnly ? 1 : 0,
            'rate_limit_per_minute' => $rateLimitPerMinute,
            'mcp_allowed' => $mcpAllowed ? 1 : 0,
        ]);
        if ($enableApi) {
            $db->updateInDatabase('api_settings', ['enabled' => 1], ['id' => 1]);
        }

        return $raw;
    }

    /**
     * Sends a request to the REST API and returns [status code, decoded JSON
     * body]. PhpBrowser has no REST helpers (module-rest is not installed),
     * so this uses its low-level _request() with a raw JSON body.
     *
     * @param array<string, mixed>|null $json
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function sendApiRequest(string $method, string $path, ?string $token, ?array $json = null): array
    {
        $browser = $this->phpBrowser();
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        if ($json !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        $browser->_request($method, '/api/v1' . $path, [], [], $server, $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null);
        $response = $browser->client->getInternalResponse();
        $body = json_decode((string) $response->getContent(), true);

        return [$response->getStatusCode(), is_array($body) ? $body : []];
    }

    /**
     * Sends one JSON-RPC request to the MCP server /mcp and returns
     * [status code, decoded JSON body].
     *
     * @param array<string, mixed> $params
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function sendMcpRequest(?string $token, string $method, array $params = []): array
    {
        $browser = $this->phpBrowser();
        $server = ['HTTP_ACCEPT' => 'application/json, text/event-stream', 'CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params === [] ? new \stdClass() : $params];
        $browser->_request('POST', '/mcp', [], [], $server, json_encode($message, JSON_THROW_ON_ERROR));
        $response = $browser->client->getInternalResponse();
        $body = json_decode((string) $response->getContent(), true);

        return [$response->getStatusCode(), is_array($body) ? $body : []];
    }

    /**
     * Calls an MCP tool and returns [isError, structured result or error text].
     *
     * @param array<string, mixed> $arguments
     * @return array{0: bool, 1: mixed}
     */
    public function callMcpTool(string $token, string $tool, array $arguments = []): array
    {
        [$status, $body] = $this->sendMcpRequest($token, 'tools/call', ['name' => $tool, 'arguments' => $arguments === [] ? new \stdClass() : $arguments]);
        $this->assertSame(200, $status, 'tools/call ' . $tool . ' answered HTTP ' . $status);
        $result = $body['result'] ?? [];
        if (($result['isError'] ?? false) === true) {
            return [true, (string) ($result['content'][0]['text'] ?? '')];
        }

        return [false, $result['structuredContent'] ?? null];
    }

    private function phpBrowser(): PhpBrowser
    {
        /** @var PhpBrowser $browser */
        $browser = $this->getModule('PhpBrowser');

        return $browser;
    }
}
