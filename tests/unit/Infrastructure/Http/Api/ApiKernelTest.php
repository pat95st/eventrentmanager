<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api;

use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ApiSettings;
use App\Infrastructure\Http\Api\ApiKernel;
use App\Infrastructure\Http\Api\ApiRequest;
use App\Infrastructure\Http\Api\ApiResponse;
use App\Infrastructure\Http\Api\RateLimit\RateLimiterInterface;
use App\Infrastructure\Http\Api\RateLimit\RateLimitResult;
use App\Infrastructure\Http\Request;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryApiRequestLogRepository;
use Tests\Support\Fakes\InMemoryApiSettingsRepository;
use Tests\Support\Fakes\InMemoryApiTokenRepository;

final class ApiKernelTest extends Unit
{
    private InMemoryApiTokenRepository $tokens;
    private InMemoryApiRequestLogRepository $log;
    private InMemoryApiSettingsRepository $settings;
    /** @var array<string, int> */
    private array $hits = [];

    protected function _before(): void
    {
        $this->tokens = new InMemoryApiTokenRepository();
        $this->log = new InMemoryApiRequestLogRepository();
        $this->settings = new InMemoryApiSettingsRepository(new ApiSettings(enabled: true, rateLimitPerMinute: 2));
        $this->hits = [];
    }

    public function testDisabledApiAnswers404(): void
    {
        $this->settings->save(new ApiSettings(enabled: false));
        $this->addToken('secret', [ApiScope::CustomersRead]);

        $this->assertSame(404, $this->call('GET', [ApiScope::CustomersRead], 'secret')->status);
    }

    public function testMissingAndUnknownTokensAre401(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead]);

        $this->assertSame(401, $this->call('GET', [ApiScope::CustomersRead], null)->status);
        $this->assertSame(401, $this->call('GET', [ApiScope::CustomersRead], 'other')->status);
    }

    public function testDisabledTokenIs401(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead], enabled: false);

        $this->assertSame(401, $this->call('GET', [ApiScope::CustomersRead], 'secret')->status);
    }

    public function testIpAllowlistIsEnforced(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead], ipAllowlist: '203.0.113.0/24');

        $this->assertSame(403, $this->call('GET', [ApiScope::CustomersRead], 'secret', ip: '198.51.100.1')->status);
        $this->assertSame(200, $this->call('GET', [ApiScope::CustomersRead], 'secret', ip: '203.0.113.7')->status);
    }

    public function testMissingScopeIs403AndNamesTheScope(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead]);

        $response = $this->call('POST', [ApiScope::CustomersWrite], 'secret');

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('customers:write', $response->body()['error']);
    }

    public function testReadOnlyTokenCannotWriteEvenWithWriteScope(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead, ApiScope::CustomersWrite], readOnly: true);

        $this->assertSame(200, $this->call('GET', [ApiScope::CustomersRead], 'secret')->status);
        $this->assertSame(403, $this->call('POST', [ApiScope::CustomersWrite], 'secret')->status);
    }

    public function testAllRequiredScopesMustBeGranted(): void
    {
        $this->addToken('secret', [ApiScope::InvoicesRead]);

        $this->assertSame(403, $this->call('POST', [ApiScope::InvoicesRead, ApiScope::EmailSend], 'secret')->status);
    }

    public function testRateLimitReturns429WithRetryAfter(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead]);

        $this->assertSame(200, $this->call('GET', [ApiScope::CustomersRead], 'secret')->status);
        $second = $this->call('GET', [ApiScope::CustomersRead], 'secret');
        $this->assertSame('0', $second->headers['X-RateLimit-Remaining']);
        $third = $this->call('GET', [ApiScope::CustomersRead], 'secret');

        $this->assertSame(429, $third->status);
        $this->assertSame('30', $third->headers['Retry-After']);
    }

    public function testTokenSpecificLimitOverridesTheDefault(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead], rateLimit: 0); // unlimited

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, $this->call('GET', [ApiScope::CustomersRead], 'secret')->status);
        }
        $this->assertSame([], $this->hits, 'no limiter call when unlimited');
    }

    public function testDomainExceptionsAreMappedToStatusCodes(): void
    {
        $this->addToken('secret', [ApiScope::CustomersWrite], rateLimit: 0);

        $validation = $this->call('POST', [ApiScope::CustomersWrite], 'secret', handler: static fn () => throw new ValidationException(['lastName' => 'Pflicht']));
        $this->assertSame(422, $validation->status);
        $this->assertSame(['last_name' => 'Pflicht'], $validation->body()['errors']);
        $this->assertSame(404, $this->call('POST', [ApiScope::CustomersWrite], 'secret', handler: static fn () => throw new NotFoundException('x'))->status);
        $this->assertSame(409, $this->call('POST', [ApiScope::CustomersWrite], 'secret', handler: static fn () => throw new ConflictException('x'))->status);
        $this->assertSame(500, @$this->call('POST', [ApiScope::CustomersWrite], 'secret', handler: static fn () => throw new \RuntimeException('boom'))->status);
    }

    public function testJsonBodyIsDecodedAndInvalidJsonIs400(): void
    {
        $this->addToken('secret', [ApiScope::CustomersWrite], rateLimit: 0);
        $echo = static fn (ApiRequest $r) => ApiResponse::json($r->body);

        $this->assertSame(['city' => 'Berlin'], $this->call('PATCH', [ApiScope::CustomersWrite], 'secret', body: '{"city":"Berlin"}', handler: $echo)->body());
        $this->assertSame(400, $this->call('PATCH', [ApiScope::CustomersWrite], 'secret', body: '{oops', handler: $echo)->status);
        $this->assertSame(400, $this->call('PATCH', [ApiScope::CustomersWrite], 'secret', body: '[1,2]', handler: $echo)->status);
    }

    public function testWriteRequestsAreLoggedReadsAreNot(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead], rateLimit: 0);

        $this->call('GET', [ApiScope::CustomersRead], 'secret');
        $this->call('POST', [ApiScope::CustomersWrite], 'secret');

        $this->assertCount(1, $this->log->entries);
        $this->assertSame('POST', $this->log->entries[0]->method);
        $this->assertSame(403, $this->log->entries[0]->statusCode);
        $this->assertSame('192.0.2.1', $this->log->entries[0]->ip);
    }

    public function testLastUsedIsUpdatedOnSuccess(): void
    {
        $this->addToken('secret', [ApiScope::CustomersRead]);

        $this->call('GET', [ApiScope::CustomersRead], 'secret');

        $this->assertEquals(new DateTimeImmutable('2026-11-17 10:00:30'), $this->tokens->findById('t1')?->lastUsedAt);
    }

    public function testBearerTokenParsing(): void
    {
        $this->assertSame('abc', ApiKernel::bearerToken(['HTTP_AUTHORIZATION' => 'Bearer abc']));
        $this->assertSame('abc', ApiKernel::bearerToken(['REDIRECT_HTTP_AUTHORIZATION' => 'bearer  abc ']));
        $this->assertNull(ApiKernel::bearerToken(['HTTP_AUTHORIZATION' => 'Basic abc']));
        $this->assertNull(ApiKernel::bearerToken([]));
    }

    /**
     * @param list<ApiScope> $scopes
     */
    private function addToken(string $raw, array $scopes, bool $readOnly = false, ?int $rateLimit = null, bool $enabled = true, ?string $ipAllowlist = null): void
    {
        $now = new DateTimeImmutable('2026-01-01');
        $this->tokens->save(new ApiToken('t1', 'Test', hash('sha256', $raw), substr($raw, -4), $ipAllowlist, $enabled, null, $now, $scopes, $readOnly, $rateLimit));
    }

    /** @param list<ApiScope> $scopes */
    private function call(string $method, array $scopes, ?string $token, ?string $body = null, ?callable $handler = null, string $ip = '192.0.2.1'): ApiResponse
    {
        $limiter = new class ($this->hits) implements RateLimiterInterface {
            /** @param array<string, int> $hits */
            public function __construct(private array &$hits)
            {
            }

            public function hit(string $tokenId, int $limit, DateTimeImmutable $now): RateLimitResult
            {
                $this->hits[$tokenId] = ($this->hits[$tokenId] ?? 0) + 1;

                return new RateLimitResult($limit, $this->hits[$tokenId], RateLimitResult::window($now)[1]);
            }
        };
        $kernel = new ApiKernel(
            $this->settings,
            $this->tokens,
            $this->log,
            static fn () => $limiter,
            static fn () => new DateTimeImmutable('2026-11-17 10:00:30'),
        );
        $server = ['REMOTE_ADDR' => $ip];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        return $kernel->handle(
            new Request($method, '/api/v1/customers', [], [], []),
            $scopes,
            $handler ?? static fn () => ApiResponse::json(['ok' => true]),
            $server,
            $body ?? '',
        );
    }
}
