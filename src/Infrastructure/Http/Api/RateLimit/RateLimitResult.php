<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\RateLimit;

use DateTimeImmutable;

final class RateLimitResult
{
    public function __construct(
        public readonly int $limit,
        public readonly int $hits,
        public readonly DateTimeImmutable $resetAt,
    ) {
    }

    public function allowed(): bool
    {
        return $this->limit <= 0 || $this->hits <= $this->limit;
    }

    public function remaining(): int
    {
        return max(0, $this->limit - $this->hits);
    }

    public function retryAfterSeconds(DateTimeImmutable $now): int
    {
        return max(1, $this->resetAt->getTimestamp() - $now->getTimestamp());
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        if ($this->limit <= 0) {
            return [];
        }

        return [
            'X-RateLimit-Limit' => (string) $this->limit,
            'X-RateLimit-Remaining' => (string) $this->remaining(),
            'X-RateLimit-Reset' => (string) $this->resetAt->getTimestamp(),
        ];
    }

    /** Start of the minute containing $now, and the start of the next one. */
    public static function window(DateTimeImmutable $now): array
    {
        $start = $now->setTime((int) $now->format('H'), (int) $now->format('i'), 0);

        return [$start, $start->modify('+1 minute')];
    }
}
