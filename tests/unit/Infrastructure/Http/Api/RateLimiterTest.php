<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api;

use App\Domain\Settings\ApiSettings;
use App\Infrastructure\Http\Api\RateLimit\FallbackRateLimiter;
use App\Infrastructure\Http\Api\RateLimit\RateLimiterInterface;
use App\Infrastructure\Http\Api\RateLimit\RateLimiterUnavailable;
use App\Infrastructure\Http\Api\RateLimit\RateLimitResult;
use App\Infrastructure\Http\Api\RateLimit\RedisClient;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class RateLimiterTest extends Unit
{
    public function testResultHeadersAndRetryAfter(): void
    {
        $now = new DateTimeImmutable('2026-11-17 10:15:42');
        [$start, $reset] = RateLimitResult::window($now);
        $this->assertSame('2026-11-17 10:15:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-17 10:16:00', $reset->format('Y-m-d H:i:s'));

        $result = new RateLimitResult(10, 11, $reset);
        $this->assertFalse($result->allowed());
        $this->assertSame(0, $result->remaining());
        $this->assertSame(18, $result->retryAfterSeconds($now));
        $this->assertSame('10', $result->headers()['X-RateLimit-Limit']);
        $this->assertTrue((new RateLimitResult(0, 999, $reset))->allowed(), 'limit 0 = unlimited');
    }

    public function testFallbackUsesSecondaryWhenPrimaryIsUnavailable(): void
    {
        $errors = [];
        $limiter = new FallbackRateLimiter(
            static fn () => throw new RateLimiterUnavailable('Verbindung abgelehnt'),
            $this->fixedLimiter(5),
            static function (?string $error) use (&$errors): void {
                $errors[] = $error;
            },
        );

        $result = @$limiter->hit('t', 10, new DateTimeImmutable());

        $this->assertSame(5, $result->hits);
        $this->assertSame(['Verbindung abgelehnt'], $errors);
    }

    public function testFallbackReportsRecoveryOfThePrimary(): void
    {
        $errors = [];
        $limiter = new FallbackRateLimiter(
            fn () => $this->fixedLimiter(1),
            $this->fixedLimiter(99),
            static function (?string $error) use (&$errors): void {
                $errors[] = $error;
            },
        );

        $this->assertSame(1, $limiter->hit('t', 10, new DateTimeImmutable())->hits);
        $this->assertSame([null], $errors);
    }

    public function testRedisAddressFromSettings(): void
    {
        $this->assertSame('tcp://127.0.0.1:6379', RedisClient::address(new ApiSettings()));
        $this->assertSame('tls://redis.example.org:6380', RedisClient::address(new ApiSettings(redisConnection: ApiSettings::REDIS_TLS, redisHost: 'redis.example.org', redisPort: 6380)));
        $this->assertSame('tcp://[::1]:6379', RedisClient::address(new ApiSettings(redisHost: '::1')));
        $this->assertSame('unix:///run/redis/redis.sock', RedisClient::address(new ApiSettings(redisConnection: ApiSettings::REDIS_SOCKET, redisSocketPath: '/run/redis/redis.sock')));
    }

    public function testUnreachableRedisRaisesRateLimiterUnavailable(): void
    {
        $this->expectException(RateLimiterUnavailable::class);
        (new RedisClient('unix:///nonexistent/redis.sock', 0.2))->command('PING');
    }

    private function fixedLimiter(int $hits): RateLimiterInterface
    {
        return new class ($hits) implements RateLimiterInterface {
            public function __construct(private readonly int $hits)
            {
            }

            public function hit(string $tokenId, int $limit, DateTimeImmutable $now): RateLimitResult
            {
                return new RateLimitResult($limit, $this->hits, $now->modify('+1 minute'));
            }
        };
    }
}
