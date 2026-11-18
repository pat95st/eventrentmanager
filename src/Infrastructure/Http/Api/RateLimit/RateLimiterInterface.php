<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\RateLimit;

use DateTimeImmutable;

/** Counts API requests per token in fixed one-minute windows. */
interface RateLimiterInterface
{
    /**
     * Registers one hit for the token in the current minute and returns the
     * resulting state. Implementations throw RateLimiterUnavailable when their
     * backend cannot be reached.
     */
    public function hit(string $tokenId, int $limit, DateTimeImmutable $now): RateLimitResult;
}
