<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Api;

use App\Application\Api\Command\CreateApiToken;
use App\Application\Api\Command\CreateApiTokenHandler;
use App\Application\Api\Command\UpdateApiToken;
use App\Application\Api\Command\UpdateApiTokenHandler;
use App\Application\Settings\Command\UpdateApiSettings;
use App\Application\Settings\Command\UpdateApiSettingsHandler;
use App\Domain\Api\ApiScope;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ApiSettings;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryApiSettingsRepository;
use Tests\Support\Fakes\InMemoryApiTokenRepository;

final class ApiTokenCommandsTest extends Unit
{
    public function testCreatesTokenWithScopesReadOnlyAndRateLimit(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $created = (new CreateApiTokenHandler($repository))->handle(new CreateApiToken('Shop', null, ['articles:read', 'bogus'], true, '120'));

        $token = $repository->findById($created->id);
        $this->assertNotNull($token);
        $this->assertSame([ApiScope::ArticlesRead], $token->scopes);
        $this->assertTrue($token->readOnly);
        $this->assertSame(120, $token->rateLimitPerMinute);
        $this->assertSame(hash('sha256', $created->rawToken), $token->tokenHash);
    }

    public function testRequiresAScopeAndAValidRateLimit(): void
    {
        try {
            (new CreateApiTokenHandler(new InMemoryApiTokenRepository()))->handle(new CreateApiToken('Shop', null, [], false, 'viel'));
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('scopes', $e->errors());
            $this->assertArrayHasKey('rateLimitPerMinute', $e->errors());
        }
    }

    public function testUpdateChangesScopesAndClearsTheLimit(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $created = (new CreateApiTokenHandler($repository))->handle(new CreateApiToken('Shop', null, ['articles:read'], true, '10'));

        (new UpdateApiTokenHandler($repository))->handle(new UpdateApiToken($created->id, 'Shop 2', null, ['customers:read', 'customers:write'], false, ''));

        $token = $repository->findById($created->id);
        $this->assertSame('Shop 2', $token?->name);
        $this->assertSame([ApiScope::CustomersRead, ApiScope::CustomersWrite], $token?->scopes);
        $this->assertFalse($token?->readOnly);
        $this->assertNull($token?->rateLimitPerMinute);
    }

    public function testSettingsKeepThePasswordUnlessReplacedOrCleared(): void
    {
        $repository = new InMemoryApiSettingsRepository(new ApiSettings(redisPassword: 'alt'));
        $handler = new UpdateApiSettingsHandler($repository);

        $handler->handle(new UpdateApiSettings(true, '300', 'redis', 'tcp', 'redis.local', '6380', null, null, ''));
        $settings = $repository->get();
        $this->assertSame('alt', $settings->redisPassword);
        $this->assertSame(300, $settings->rateLimitPerMinute);
        $this->assertSame('redis.local', $settings->redisHost);
        $this->assertSame(6380, $settings->redisPort);

        $handler->handle(new UpdateApiSettings(true, redisPassword: 'neu'));
        $this->assertSame('neu', $repository->get()->redisPassword);

        $handler->handle(new UpdateApiSettings(true, clearRedisPassword: true));
        $this->assertNull($repository->get()->redisPassword);
    }

    public function testTokenCanBeReleasedForMcp(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $created = (new CreateApiTokenHandler($repository))->handle(new CreateApiToken('Claude', null, ['customers:read'], false, null, true));
        $this->assertTrue($repository->findById($created->id)?->mcpAllowed);

        (new UpdateApiTokenHandler($repository))->handle(new UpdateApiToken($created->id, 'Claude', null, ['customers:read']));
        $this->assertFalse($repository->findById($created->id)?->mcpAllowed, 'unticked checkbox revokes the release');

        $shop = (new CreateApiTokenHandler($repository))->handle(new CreateApiToken('Shop', null, ['articles:read']));
        $this->assertFalse($repository->findById($shop->id)?->mcpAllowed, 'tokens are not released for MCP by default');
    }

    public function testMcpSwitchesAreSavedAndRequireTheApi(): void
    {
        $repository = new InMemoryApiSettingsRepository(new ApiSettings());
        $handler = new UpdateApiSettingsHandler($repository);

        $handler->handle(new UpdateApiSettings(true, mcpEnabled: true, mcpReadOnly: true));
        $this->assertTrue($repository->get()->mcpEnabled);
        $this->assertTrue($repository->get()->mcpReadOnly);
        $this->assertTrue($repository->get()->mcpAvailable());

        try {
            $handler->handle(new UpdateApiSettings(false, mcpEnabled: true));
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('mcpEnabled', $e->errors());
        }

        $handler->handle(new UpdateApiSettings(false));
        $this->assertFalse($repository->get()->mcpEnabled);
        $this->assertFalse($repository->get()->mcpAvailable());
    }

    public function testSocketConnectionRequiresAPath(): void
    {
        $this->expectException(ValidationException::class);
        (new UpdateApiSettingsHandler(new InMemoryApiSettingsRepository()))->handle(new UpdateApiSettings(true, '60', 'redis', 'socket', redisSocketPath: ''));
    }

    public function testRejectsInvalidNumbers(): void
    {
        try {
            (new UpdateApiSettingsHandler(new InMemoryApiSettingsRepository()))->handle(new UpdateApiSettings(true, '-1', redisPort: '70000', redisTimeoutMs: '10'));
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['rateLimitPerMinute', 'redisPort', 'redisTimeoutMs'], array_keys($e->errors()));
        }
    }
}
