<?php

declare(strict_types=1);

namespace App\Domain\Api;

use DateTimeImmutable;

/** One write request (POST/PATCH/PUT/DELETE) made through the REST API (migration 175). */
final class ApiRequestLogEntry
{
    public function __construct(
        public readonly string $tokenId,
        public readonly string $tokenName,
        public readonly string $method,
        public readonly string $path,
        public readonly int $statusCode,
        public readonly ?string $ip,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
