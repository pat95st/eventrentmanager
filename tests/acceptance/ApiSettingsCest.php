<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/** Einstellungen › API: tokens with scopes / read-only and the rate limit settings. */
final class ApiSettingsCest
{
    public function _before(AcceptanceTester $I): void
    {
        $I->loginAsAdmin();
    }

    public function createsATokenWithScopes(AcceptanceTester $I): void
    {
        $I->amOnPage('/einstellungen/api');
        $I->see('Zugriffs-Tokens');
        $I->submitForm('form[action="/einstellungen/api/tokens"]', [
            'name' => 'Website',
            'scopes' => ['articles:read', 'customers:write'],
            'read_only' => '1',
            'rate_limit_per_minute' => '120',
        ]);
        $I->see('Neues Token erstellt');
        $I->seeInDatabase('api_tokens', [
            'name' => 'Website',
            'scopes' => '["customers:write","articles:read"]',
            'read_only' => 1,
            'rate_limit_per_minute' => 120,
        ]);
    }

    public function requiresAtLeastOneScope(AcceptanceTester $I): void
    {
        $I->amOnPage('/einstellungen/api');
        $I->submitForm('form[action="/einstellungen/api/tokens"]', ['name' => 'Ohne Scopes']);
        $I->see('Bitte mindestens eine Berechtigung');
        $I->dontSeeInDatabase('api_tokens', ['name' => 'Ohne Scopes']);
    }

    public function savesRedisRateLimitSettings(AcceptanceTester $I): void
    {
        $I->amOnPage('/einstellungen/api');
        $I->submitForm('form[action="/einstellungen/api"]', [
            'enabled' => '1',
            'rate_limit_per_minute' => '300',
            'rate_limit_backend' => 'redis',
            'redis_connection' => 'socket',
            'redis_socket_path' => '/run/redis/redis.sock',
            'redis_password' => 'geheim',
            'redis_database' => '2',
            'redis_key_prefix' => 'mv:',
            'redis_timeout_ms' => '500',
        ]);
        $I->see('API-Einstellungen wurden gespeichert.');
        $I->seeInDatabase('api_settings', [
            'enabled' => 1,
            'rate_limit_per_minute' => 300,
            'rate_limit_backend' => 'redis',
            'redis_connection' => 'socket',
            'redis_socket_path' => '/run/redis/redis.sock',
            'redis_password' => 'geheim',
            'redis_database' => 2,
            'redis_timeout_ms' => 500,
        ]);
        // The password is never sent back to the form; an empty field keeps it.
        $I->dontSee('geheim');
    }
}
