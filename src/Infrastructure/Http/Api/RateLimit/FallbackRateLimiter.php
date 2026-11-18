<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\RateLimit;

use Closure;
use DateTimeImmutable;

/**
 * Uses the primary limiter (Redis) and falls back to the secondary one
 * (database) when the primary is unavailable. $onError receives the error
 * message (or null after a successful primary hit, so a previously recorded
 * error can be cleared).
 */
final class FallbackRateLimiter implements RateLimiterInterface
{
    /** @param Closure(?string): void $onError */
    public function __construct(
        private readonly Closure $primary,
        private readonly RateLimiterInterface $fallback,
        private readonly Closure $onError,
    ) {
    }

    public function hit(string $tokenId, int $limit, DateTimeImmutable $now): RateLimitResult
    {
        try {
            /** @var RateLimiterInterface $primary */
            $primary = ($this->primary)();
            $result = $primary->hit($tokenId, $limit, $now);
            ($this->onError)(null);

            return $result;
        } catch (RateLimiterUnavailable $e) {
            error_log('API rate limiter: Redis unavailable, falling back to database: ' . $e->getMessage());
            ($this->onError)($e->getMessage());

            return $this->fallback->hit($tokenId, $limit, $now);
        }
    }
}
