<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/** Last calculated route per event, keyed by a hash of its input. */
interface RouteCacheRepositoryInterface
{
    /** @return array<string, mixed>|null payload when the stored hash matches */
    public function find(string $rentalId, string $hash): ?array;

    /** @param array<string, mixed> $payload */
    public function store(string $rentalId, string $hash, array $payload): void;
}
