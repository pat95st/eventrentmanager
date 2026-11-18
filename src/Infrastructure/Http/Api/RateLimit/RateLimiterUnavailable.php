<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\RateLimit;

use RuntimeException;

/** The rate limit backend (e.g. Redis) is not reachable or answered with an error. */
final class RateLimiterUnavailable extends RuntimeException
{
}
