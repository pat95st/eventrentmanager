<?php

declare(strict_types=1);

namespace App\Domain\Api;

use DateTimeImmutable;

interface ApiRequestLogRepositoryInterface
{
    public const RETENTION_DAYS = 90;

    public function add(ApiRequestLogEntry $entry): void;

    /** @return list<ApiRequestLogEntry> newest first */
    public function latest(int $limit, ?string $tokenId = null): array;

    public function purgeOlderThan(DateTimeImmutable $before): void;
}
