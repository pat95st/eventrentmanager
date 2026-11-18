<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Api\ApiRequestLogEntry;
use App\Domain\Api\ApiRequestLogRepositoryInterface;
use DateTimeImmutable;

/** In-memory test double for ApiRequestLogRepositoryInterface. */
final class InMemoryApiRequestLogRepository implements ApiRequestLogRepositoryInterface
{
    /** @var list<ApiRequestLogEntry> */
    public array $entries = [];

    public function add(ApiRequestLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function latest(int $limit, ?string $tokenId = null): array
    {
        $entries = array_values(array_filter($this->entries, static fn (ApiRequestLogEntry $e) => $tokenId === null || $e->tokenId === $tokenId));

        return array_slice(array_reverse($entries), 0, $limit);
    }

    public function purgeOlderThan(DateTimeImmutable $before): void
    {
        $this->entries = array_values(array_filter($this->entries, static fn (ApiRequestLogEntry $e) => $e->createdAt >= $before));
    }
}
