<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\RateLimit;

use DateTimeImmutable;

/**
 * Fixed-window counter in Redis: INCR on "<prefix><token>:<window>", and on
 * the first hit of a window an EXPIRE so Redis removes old keys itself.
 */
final class RedisRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly RedisClient $client,
        private readonly string $keyPrefix,
    ) {
    }

    public function hit(string $tokenId, int $limit, DateTimeImmutable $now): RateLimitResult
    {
        [$start, $reset] = RateLimitResult::window($now);
        $key = $this->keyPrefix . $tokenId . ':' . $start->format('YmdHi');

        $hits = $this->client->command('INCR', $key);
        if (!is_int($hits)) {
            throw new RateLimiterUnavailable('Unerwartete Antwort von Redis auf INCR.');
        }
        if ($hits === 1) {
            $this->client->command('EXPIRE', $key, '120');
        }

        return new RateLimitResult($limit, $hits, $reset);
    }
}
